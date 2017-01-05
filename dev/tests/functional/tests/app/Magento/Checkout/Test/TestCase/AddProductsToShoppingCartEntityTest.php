<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Checkout\Test\TestCase;

use Magento\Catalog\Test\Page\Product\CatalogProductView;
use Magento\Checkout\Test\Page\CheckoutCart;
use Magento\Mtf\Client\BrowserInterface;
use Magento\Mtf\Fixture\FixtureFactory;
use Magento\Mtf\TestCase\Injectable;
use Magento\Mtf\TestStep\TestStepFactory;
use Magento\Backend\Test\Page\Adminhtml\SystemConfigEdit;
use Magento\Mtf\Util\Command\Cli\Cache;

/**
 * Preconditions:
 * 1. All type products is created
 *
 * Steps:
 * 1. Navigate to frontend
 * 2. Open test product page
 * 3. Add to cart test product
 * 4. Perform all asserts
 *
 * @group Shopping_Cart
 * @ZephyrId MAGETWO-25382, MAGETWO-42677
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AddProductsToShoppingCartEntityTest extends Injectable
{
    /* tags */
    const MVP = 'yes';
    const SEVERITY = 'S0';
    /* end tags */

    /**
     * Browser interface
     *
     * @var BrowserInterface
     */
    protected $browser;

    /**
     * Fixture factory
     *
     * @var FixtureFactory
     */
    protected $fixtureFactory;

    /**
     * Catalog product view page
     *
     * @var CatalogProductView
     */
    protected $catalogProductView;

    /**
     * Checkout cart page
     *
     * @var CheckoutCart
     */
    protected $cartPage;

    /**
     * Configuration data.
     *
     * @var string
     */
    private $configData;

    /**
     * Factory for Test Steps.
     *
     * @var TestStepFactory
     */
    private $testStepFactory;

    /**
     * Should cache be flushed.
     *
     * @var bool
     */
    private $flushCache;

    /**
     * "Configuration" page in Admin panel.
     *
     * @var SystemConfigEdit
     */
    private $configurationAdminPage;

    /**
     * Cache CLI.
     *
     * @var Cache
     */
    private $cache;

    /**
     * Prepare test data.
     *
     * @param BrowserInterface $browser
     * @param FixtureFactory $fixtureFactory
     * @param CatalogProductView $catalogProductView
     * @param CheckoutCart $cartPage
     * @param TestStepFactory $testStepFactory
     * @param Cache $cache
     * @return void
     */
    public function __prepare(
        BrowserInterface $browser,
        FixtureFactory $fixtureFactory,
        CatalogProductView $catalogProductView,
        CheckoutCart $cartPage,
        TestStepFactory $testStepFactory,
        Cache $cache
    ) {
        $this->browser = $browser;
        $this->fixtureFactory = $fixtureFactory;
        $this->catalogProductView = $catalogProductView;
        $this->cartPage = $cartPage;
        $this->testStepFactory = $testStepFactory;
        $this->cache = $cache;
    }

    /**
     * Run test add products to shopping cart.
     *
     * @param array $productsData
     * @param array $cart
     * @param string|null $configData [optional]
     * @param bool $flushCache [optional]
     * @return array
     */
    public function test(array $productsData, array $cart, $configData = null, $flushCache = false)
    {
        // Preconditions
        $this->configData = $configData;
        $this->flushCache = $flushCache;

        $this->testStepFactory->create(
            \Magento\Config\Test\TestStep\SetupConfigurationStep::class,
            ['configData' => $this->configData, 'flushCache' => $this->flushCache]
        )->run();

        if ($this->configData == 'enable_https_frontend_admin') {
            $_ENV['app_backend_url'] = mb_ereg_replace ("(http[s]?)", 'https', $_ENV['app_backend_url']);
            $_ENV['app_frontend_url'] = mb_ereg_replace ("(http[s]?)", 'https', $_ENV['app_frontend_url']);

        }
        $products = $this->prepareProducts($productsData);

        // Steps
        $this->addToCart($products);

        $cart['data']['items'] = ['products' => $products];
        return ['cart' => $this->fixtureFactory->createByCode('cart', $cart)];
    }

    /**
     * Create products.
     *
     * @param array $productList
     * @return array
     */
    protected function prepareProducts(array $productList)
    {
        $addToCartStep = $this->testStepFactory->create(
            \Magento\Catalog\Test\TestStep\CreateProductsStep::class,
            ['products' => $productList]
        );

        $result = $addToCartStep->run();
        return $result['products'];
    }

    /**
     * Add products to cart.
     *
     * @param array $products
     * @return void
     */
    protected function addToCart(array $products)
    {
        $addToCartStep = $this->testStepFactory->create(
            \Magento\Checkout\Test\TestStep\AddProductsToTheCartStep::class,
            ['products' => $products]
        );
        $addToCartStep->run();
    }

    /**
     * Clean data after running test.
     *
     * @return void
     */
    public function tearDown()
    {
        // Workaround until MTA-3879 is delivered.
        if ($this->configData == 'enable_https_frontend_admin') {
            $this->getSystemConfigEditPage()->open();
            $this->getSystemConfigEditPage()->getForm()
                ->getGroup('web', 'secure')->setValue('web', 'secure', 'use_in_frontend', 'No');
            $this->getSystemConfigEditPage()->getForm()
                ->getGroup('web', 'secure')->setValue('web', 'secure', 'use_in_adminhtml', 'No');
            $this->getSystemConfigEditPage()->getForm()
                ->getGroup('web', 'secure')->setValue('web', 'secure', 'base_url', $this->getBaseUrl());
            $this->getSystemConfigEditPage()->getForm()
                ->getGroup('web', 'secure')->setValue('web', 'secure', 'base_link_url', $this->getBaseUrl());
            $this->getSystemConfigEditPage()->getPageActions()->save();
            $this->cache->flush();
        }
    }

    /**
     * Get base URL.
     *
     * @param bool $useHttps
     * @return string
     */
    private function getBaseUrl($useHttps = false)
    {
        $protocol = $useHttps ? 'https' : 'http';
        return mb_ereg_replace ("(http[s]?)", $protocol, $_ENV['app_frontend_url']);
    }

    /**
     * Create System Config Edit Page.
     *
     * @return SystemConfigEdit
     */
    private function getSystemConfigEditPage()
    {
        if (null === $this->configurationAdminPage) {
            $this->configurationAdminPage = $this->testStepFactory->create(
                \Magento\Backend\Test\Page\Adminhtml\SystemConfigEdit::class
            );
        }

        return $this->configurationAdminPage;
    }
}
