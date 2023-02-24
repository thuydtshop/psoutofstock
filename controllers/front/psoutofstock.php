<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\PrestaShop\Adapter\Category\CategoryProductSearchProvider;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use PrestaShop\PrestaShop\Adapter\Search\SearchProductSearchProvider;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Product\Search\Pagination;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;

class PsOutofstockPsoutOfstockModuleFrontController extends ProductListingFrontController
{
    public $module;

    protected $psoutofstocklist;

    public function __construct()
    {
        /** @var BlockWishList $module */
        $module = Module::getInstanceByName('psoutofstock');
        $this->module = $module;

        if (empty($this->module->active)) {
            Tools::redirect('index');
        }

        parent::__construct();

        $this->controller_type = 'modulefront';
    }

    /**
     * Initializes controller.
     *
     * @see FrontController::init()
     *
     * @throws PrestaShopException
     */
    public function init()
    {
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        parent::initContent();

        $this->doProductSearch(
            '../../../modules/psoutofstock/views/templates/list.tpl',
            []
        );
    }

    public function getListingLabel()
    {
        return $this->trans(
            'Wieder lieferbar',
            array(),
            'Modules.Psoutofstock.Shop'
        );
    }

    protected function getProductSearchQuery()
    {
        $query = new ProductSearchQuery();
        $query->setSortOrder(
            new SortOrder(
                'product',
                Tools::getProductsOrder('by'),
                Tools::getProductsOrder('way')
            )
        );

        return $query;
    }

    protected function getDefaultProductSearchProvider()
    {
        return new SearchProductSearchProvider(
            $this->getTranslator()
        );
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = [
            'title' => $this->module->l('Wieder lieferbar'),
            'url' => Context::getContext()->link->getModuleLink('psoutofstock', 'psoutofstock'),
        ];

        return $breadcrumb;
    }




    private function getTotalProductSTock($qry)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('out_stock');
        $query->where('date_update  >= DATE_ADD(CURDATE(), INTERVAL "-15" DAY) ');

        $query->orderBy('date_update desc');
        $query->groupBy('id_product,id_product_attribute');

        $context = Context::getContext();
        $id_shop = (int) $context->shop->id;
        // $id_lang = (int) $context->language->id;

        $productOutOfStock = Db::getInstance()->executeS($query);

        if(empty($productOutOfStock)) {
            return false;
        }

        $backList=array();

        // get all stock 0 before get when stock back it
        foreach ($productOutOfStock as $stock) {
            // check if product qty >1
            $query = new DbQuery();
            $query->select('quantity');
            $query->from('stock_available');
            $query->where('quantity > 0 AND id_shop = '.$id_shop.' AND id_product = '.$stock["id_product"].' AND id_product_attribute = '.$stock["id_product_attribute"].' ');
            $totalAddedQty = Db::getInstance()->getValue($query);

            if(!empty($totalAddedQty)){
                $backList[]=$stock;
            }
        }
        
        if(empty($backList))
            return false;

        return  count($backList);
    }


    protected function getBackStockProduct($qry)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('out_stock');
        $query->where('date_update  >= DATE_ADD(CURDATE(), INTERVAL "-15" DAY) '); // why 15 days?
        
        $query->orderBy('date_update desc');
        $query->orderBy('id_product desc');
        $query->orderBy('id_product_attribute desc');

        $query->groupBy('id_product, id_product_attribute');

        $context = Context::getContext();
        $id_shop = (int) $context->shop->id;
        // $id_lang = (int) $context->language->id;

        $productOutOfStock = Db::getInstance()->executeS($query);

        if(empty($productOutOfStock)) {
            return false;
        }

        $backList = array();

        // get all stock 0 before get when stock back it
        foreach ($productOutOfStock as $stock) {
            // check if product qty >1
            $query = new DbQuery();
            $query->select('quantity');
            $query->from('stock_available');
            $query->where('quantity > 0 AND id_shop = '.$id_shop.' and id_product = '.$stock["id_product"].' AND id_product_attribute = '.$stock["id_product_attribute"].' ');
            $totalAddedQty = Db::getInstance()->getValue($query);

            if(!empty($totalAddedQty)){
                $backList[]=$stock;
            }
        }
        
        if(empty($backList)) {
            return false;
        }
         
        if (!empty($backList)) {
            // $assembler = new ProductAssembler($this->context);

            $presenterFactory = new ProductPresenterFactory($this->context);
            $presentationSettings = $presenterFactory->getPresentationSettings();
            $presenter = new ProductListingPresenter(
                new ImageRetriever(
                    $this->context->link
                ),
                $this->context->link,
                new PriceFormatter(),
                new ProductColorsRetriever(),
                $this->context->getTranslator()
            );

            $products_for_template = array();

            if (is_array($backList)) {
                foreach ($backList as $productId) {
                    $productObject=new Product((int)$productId['id_product']);
                    if($productObject->active==0){
                        continue;
                    }

                    $product = (new ProductAssembler($this->context))
                        ->assembleProduct(array(
                            'id_product' => $productId['id_product'],
                            'id_product_attribute' => $productId['id_product_attribute']
                        ));

                    if ($product['product_type'] === 'combinations' && count($product['attributes']) > 0) {
                        $attribute = array_values($product['attributes'])[0];

                        $product['name'] = $product['name'].' - '.$attribute['name'];

                        $images = Image::getImages($this->context->language->id, $productId['id_product'], $productId['id_product_attribute']);
                        if ($images) {
                            $product['cover_image_id'] = $images[0]['id_image'];
                        }
                    }
                    
                    $products_for_template[] = $presenter->present(
                        $presentationSettings,
                        $product,
                        $this->context->language
                    );
                }
            }

            return $products_for_template;
        }

        return false;
    }

    protected function getProductSearchVariables()
    {
        $context = $this->getProductSearchContext();

        // the controller generates the query...
        $query = $this->getProductSearchQuery();

        $providers = Hook::exec(
            'productSearchProvider',
            array('query' => $query),
            null,
            true
        );

        $resultsPerPage = (int) Tools::getValue('resultsPerPage');
        if ($resultsPerPage <= 0) {
            $resultsPerPage = Configuration::get('PS_PRODUCTS_PER_PAGE');
        }

        // we need to set a few parameters from back-end preferences
        $query->setResultsPerPage($resultsPerPage)->setPage(max((int) Tools::getValue('page'), 1));

        // set the sort order if provided in the URL
        if (($encodedSortOrder = Tools::getValue('order'))) {
            $query->setSortOrder(SortOrder::newFromString(
                $encodedSortOrder
            ));
        }

        // get the parameters containing the encoded facets from the URL
        $encodedFacets = Tools::getValue('q');

        /*
         * The controller is agnostic of facets.
         * It's up to the search module to use /define them.
         *
         * Facets are encoded in the "q" URL parameter, which is passed
         * to the search provider through the query's "$encodedFacets" property.
         */
        $query->setEncodedFacets($encodedFacets);

        // We're ready to run the actual query!

        // prepare the products
        $products = $this->getBackStockProduct($query);

        $totalProductToSHow=$this->getTotalProductSTock($query);

        /** @var ProductSearchResult $result */
         $result = new ProductSearchResult();

         $result->setProducts(!empty($products) ?  $products: array())->setTotalProductsCount($totalProductToSHow);


        // render the facets
        if ($providers instanceof FacetsRendererInterface) {
            // with the provider if it wants to
            $rendered_facets = $provider->renderFacets(
                $context,
                $result
            );
            $rendered_active_filters = $provider->renderActiveFilters(
                $context,
                $result
            );
        } else {
            // with the core
            $rendered_facets = $this->renderFacets(
                $result
            );
            $rendered_active_filters = $this->renderActiveFilters(
                $result
            );
        }

         $pagination = $this->getTemplateVarPagination(
            $query,
            $result
        );

        // prepare the sort orders
        // note that, again, the product controller is sort-orders
        // agnostic
        // a module can easily add specific sort orders that it needs
        // to support (e.g. sort by "energy efficiency")
        $sort_orders = $this->getTemplateVarSortOrders(
            $result->getAvailableSortOrders(),
            $query->getSortOrder()->toString()
        );

        $sort_selected = false;
        if (!empty($sort_orders)) {
            foreach ($sort_orders as $order) {
                if (isset($order['current']) && true === $order['current']) {
                    $sort_selected = $order['label'];

                    break;
                }
            }
        }

        $searchVariables = array(
            'result' => $result,
            'label' => $this->getListingLabel(),
            'products' => !empty($products) ?  $products : array(),
            'sort_orders' => $sort_orders,
            'sort_selected' => $sort_selected,
            'pagination' => $pagination,
            'rendered_facets' => $rendered_facets,
            'rendered_active_filters' => $rendered_active_filters,
            'js_enabled' => $this->ajax,
            'current_url' => $this->updateQueryString(array(
                'q' => $result->getEncodedFacets(),
            )),
        );

        Hook::exec('filterProductSearch', array('searchVariables' => &$searchVariables));
        Hook::exec('actionProductSearchAfter', $searchVariables);

        return $searchVariables;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        return $page;
    }

    protected function getTemplateVarPagination(ProductSearchQuery $query, $result)
    {
        $pagination = new Pagination();
        $pagination
            ->setPage($query->getPage())
            ->setPagesCount(
                (int) ceil($result->getTotalProductsCount() / $query->getResultsPerPage())
            );

        $totalItems = $result->getTotalProductsCount();
        $itemsShownFrom = ($query->getResultsPerPage() * ($query->getPage() - 1)) + 1;
        $itemsShownTo = $query->getResultsPerPage() * $query->getPage();

        $pages = array_map(function ($link) {
            $link['url'] = $this->updateQueryString(array(
                'page' => $link['page'] > 1 ? $link['page'] : null,
            ));

            return $link;
        }, $pagination->buildLinks());

        //Filter next/previous link on first/last page
        $pages = array_filter($pages, function ($page) use ($pagination) {
            if ('previous' === $page['type'] && 1 === $pagination->getPage()) {
                return false;
            }
            if ('next' === $page['type'] && $pagination->getPagesCount() === $pagination->getPage()) {
                return false;
            }

            return true;
        });

        return array(
            'total_items' => $totalItems,
            'items_shown_from' => $itemsShownFrom,
            'items_shown_to' => ($itemsShownTo <= $totalItems) ? $itemsShownTo : $totalItems,
            'current_page' => $pagination->getPage(),
            'pages_count' => $pagination->getPagesCount(),
            'pages' => $pages,
            // Compare to 3 because there are the next and previous links
            'should_be_displayed' => (count($pagination->buildLinks()) > 3),
        );
    }
}
