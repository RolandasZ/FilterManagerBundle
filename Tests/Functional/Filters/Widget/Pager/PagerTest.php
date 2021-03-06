<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\FilterManagerBundle\Tests\Functional\Filters\Widget\Pager;

use ONGR\ElasticsearchBundle\Document\DocumentInterface;
use ONGR\ElasticsearchBundle\Test\ElasticsearchTestCase;
use ONGR\FilterManagerBundle\Filters\Widget\Pager\Pager;
use ONGR\FilterManagerBundle\Filters\Widget\Sort\Sort;
use ONGR\FilterManagerBundle\Search\FiltersContainer;
use ONGR\FilterManagerBundle\Search\FiltersManager;
use Symfony\Component\HttpFoundation\Request;

class PagerTest extends ElasticsearchTestCase
{
    /**
     * @return array
     */
    protected function getDataArray()
    {
        return [
            'default' => [
                'product' => [
                    [
                        '_id' => 1,
                        'color' => 'red',
                        'manufacturer' => 'a',
                        'stock' => 1,
                    ],
                    [
                        '_id' => 2,
                        'color' => 'blue',
                        'manufacturer' => 'a',
                        'stock' => 2,
                    ],
                    [
                        '_id' => 3,
                        'color' => 'red',
                        'manufacturer' => 'b',
                        'stock' => 3,
                    ],
                    [
                        '_id' => 4,
                        'color' => 'blue',
                        'manufacturer' => 'b',
                        'stock' => 4,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns filter manager.
     *
     * @param array $options
     *
     * @return FiltersManager
     */
    protected function getFiltersManager(array $options)
    {
        $container = new FiltersContainer();

        $choices = [
            [
                'label' => 'Stock ASC',
                'field' => 'stock',
                'order' => 'asc',
                'default' => false,
                'mode' => null,
            ],
        ];

        $filter = new Pager();
        $filter->setRequestField('page');
        if (isset($options['count_per_page'])) {
            $filter->setCountPerPage($options['count_per_page']);
        }
        if (isset($options['max_pages'])) {
            $filter->setMaxPages($options['max_pages']);
        }
        $container->set('pager', $filter);

        $sort = new Sort();
        $sort->setRequestField('sort');
        $sort->setChoices($choices);
        $container->set('sorting', $sort);

        return new FiltersManager($container, $this->getManager()->getRepository('AcmeTestBundle:Product'));
    }

    /**
     * Data provider for testPager().
     *
     * @return array
     */
    public function getTestPagerData()
    {
        $out = [];

        // Case #0: page with offset.
        $out[] = [
            new Request(['page' => 2]),
            ['count_per_page' => 2, 'max_pages' => 2],
            ['3', '4'],
        ];

        // Case #1: limit bigger than the total results.
        $out[] = [
            new Request(['page' => 2]),
            ['count_per_page' => 5, 'max_pages' => 2],
            [],
        ];

        // Case #2: limit bigger than the total results, should return everything.
        $out[] = [
            new Request(['page' => 1]),
            ['count_per_page' => 5, 'max_pages' => 2],
            ['1', '2', '3', '4'],
        ];

        return $out;
    }

    /**
     * Test pager filter.
     *
     * @param Request $request
     * @param int     $options
     * @param array   $expectedDocs
     *
     * @dataProvider getTestPagerData()
     */
    public function testPager(Request $request, $options, $expectedDocs)
    {
        $request->query->add(['sort' => '0']);
        $result = $this->getFiltersManager($options)->execute($request)->getResult();

        $actual = [];
        /** @var DocumentInterface $document */
        foreach ($result as $document) {
            $actual[] = $document->getId();
        }

        $this->assertSame($expectedDocs, $actual);
    }

    /**
     * Check if view data returns expected value.
     */
    public function testGetViewData()
    {
        $manager = $this->getFiltersManager(['count_per_page' => 2, 'max_pages' => 3]);
        $viewData = $manager->execute(new Request(['page' => 3]))->getFilters();

        $this->assertEquals(3, $viewData['pager']->getState()->getValue());
    }

    /**
     * Data provider for testPageRange.
     *
     * @return array
     */
    public function getPageRangeData()
    {
        $out = [];

        $options = [
            'count_per_page' => 1,
            'max_pages' => 3,
        ];

        $out[] = [
            $options,
            1,
            [1, 2, 3],
        ];

        $out[] = [
            $options,
            2,
            [1, 2, 3],
        ];

        $out[] = [
            $options,
            3,
            [2, 3, 4],
        ];

        $out[] = [
            $options,
            4,
            [2, 3, 4],
        ];

        return $out;
    }

    /**
     * Tests if pages range is generated correctly.
     *
     * @param array $options
     * @param int   $page
     * @param array $expected
     *
     * @dataProvider getPageRangeData
     */
    public function testPageRange($options, $page, $expected)
    {
        $manager = $this->getFiltersManager($options);

        $range = $manager
            ->execute(new Request(['page' => $page]))
            ->getFilters()['pager']
            ->getPager()
            ->getPages();

        $this->assertEquals($expected, $range);
    }
}
