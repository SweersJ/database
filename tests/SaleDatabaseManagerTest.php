<?php

namespace Compucie\DatabaseTest;

use Compucie\Database\Sale\Exceptions\WeekDoesNotExistException;
use Compucie\Database\Sale\Model\Product;
use DateInterval;
use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;

use Throwable;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

class SaleDatabaseManagerTest extends TestCase
{
    private TestableSaleDatabaseManager $dbm;
    protected DbTestHelper $dbh;

    protected function setUp(): void
    {
        $env = parse_ini_file(".env", true);
        if ($env) {
            $this->dbm = new TestableSaleDatabaseManager($env['sale']);
            $this->dbm->createTables();
            $this->dbh = new DbTestHelper($this->dbm->client());

            $this->dbh->truncateTables(['purchases', 'purchase_items', 'products', 'product_images']);
        }
    }

    protected function tearDown(): void
    {
        $this->dbh->truncateTables(['purchases', 'purchase_items', 'products', 'product_images']);
    }

    /**
     * @throws Exception
     */
    public function testGetPurchase(): void
    {
        $purchaseId = $this->dbm->insertPurchase();
        $purchase = $this->dbm->getPurchase($purchaseId);

        assertSame(1, $this->dbh->rowCount('purchases'));
        assertSame(1, $purchaseId);
        assertSame(1, $purchase->getPurchaseId());
        $purchasedAt = $purchase->getPurchasedAt();
        assertNotNull($purchasedAt);
        assertSame((new DateTime())->format("Y-m-d"), $purchasedAt->format('Y-m-d'));
        assertSame(0.0, $purchase->getPrice());
    }


    public function testGetPurchaseNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Purchase 1 not found.");
        $this->dbm->getPurchase(1);
    }

    /**
     * @throws Exception
     */
    public function testInsertPurchase(): void
    {
        $purchaseId = $this->dbm->insertPurchase();

        assertSame(1, $this->dbh->rowCount('purchases'));
        assertSame(1, $purchaseId);
    }

    /**
     * @throws Exception
     */
    public function testUpdatePurchase(): void
    {
        $purchaseId = $this->dbm->insertPurchase();
        $this->dbm->updatePurchase($purchaseId);

        assertSame(1, $this->dbh->rowCount('purchases'));
        assertSame(1, $this->dbh->rowCount(
            'purchases', 'purchase_id = 1 AND purchased_at IS NOT NULL AND price IS NULL')
        );

        $date = new DateTime();
        $price = 5.57;
        $this->dbm->updatePurchase($purchaseId, $date, $price);
        $purchase = $this->dbm->getPurchase($purchaseId);

        assertSame(1, $this->dbh->rowCount('purchases'));
        assertSame(1, $this->dbh->rowCount(
            'purchases', 'purchase_id = 1 AND purchased_at = ? AND price = ?',[$date->format('Y-m-d H:i:s'), $price])
        );
        assertSame(1, $purchase->getPurchaseId());
        $purchasedAt = $purchase->getPurchasedAt();
        assertNotNull($purchasedAt);
        assertSame((new DateTime())->format("Y-m-d"), $purchasedAt->format('Y-m-d'));
        assertSame(5.57, $purchase->getPrice());
    }

    /**
     * @throws Exception
     */
    public function testInsertPurchaseItem(): void
    {
        $purchaseId = $this->dbm->insertPurchase();

        $this->dbm->insertPurchaseItem($purchaseId, 1);
        $this->dbm->insertPurchaseItem($purchaseId, 1, 2);
        $this->dbm->insertPurchaseItem($purchaseId, 1, 3, "3 Cookies");
        $this->dbm->insertPurchaseItem($purchaseId, 1, 4, unitPrice: 0.69);
        $this->dbm->insertPurchaseItem($purchaseId, 1, 5, "3 Cookies", 0.69);

        assertSame(5, $this->dbh->rowCount('purchase_items'));
        assertSame(1, $this->dbh->rowCount(
            'purchase_items', 'purchase_item_id = 1 AND purchase_id = 1 AND product_id = 1 AND quantity = 1 AND name IS NULL AND unit_price IS NULL')
        );
        assertSame(1, $this->dbh->rowCount(
            'purchase_items', 'purchase_item_id = 2 AND purchase_id = 1 AND product_id = 1 AND quantity = 2 AND name IS NULL AND unit_price IS NULL')
        );
        assertSame(1, $this->dbh->rowCount(
            'purchase_items', 'purchase_item_id = 3 AND purchase_id = 1 AND product_id = 1 AND quantity = 3 AND name = "3 Cookies" AND unit_price IS NULL')
        );
        assertSame(1, $this->dbh->rowCount(
            'purchase_items', 'purchase_item_id = 4 AND purchase_id = 1 AND product_id = 1 AND quantity = 4 AND name IS NULL AND unit_price = 0.69')
        );
        assertSame(1, $this->dbh->rowCount(
            'purchase_items', 'purchase_item_id = 5 AND purchase_id = 1 AND product_id = 1 AND quantity = 5 AND name = "3 Cookies" AND unit_price = 0.69')
        );
    }

    public function testSelectProductSalesOfLastWeeksOutOfBoundsLow(): void
    {
        $this->expectException(WeekDoesNotExistException::class);
        $this->expectExceptionMessage("'0' is not a valid week.");
        $this->dbm->selectProductSalesOfLastWeeks([1, 2], 1, 0);
    }

    public function testSelectProductSalesOfLastWeeksOutOfBoundsHigh(): void
    {
        $this->expectException(WeekDoesNotExistException::class);
        $this->expectExceptionMessage("'53' is not a valid week.");
        $this->dbm->selectProductSalesOfLastWeeks([1, 2], 1, 53);
    }

    /**
     * @throws WeekDoesNotExistException
     */
    public function testSelectProductSalesOfLastWeeksNoProductIds(): void
    {
        $productSales = $this->dbm->selectProductSalesOfLastWeeks([], 1, 1);

        assertSame("[]",json_encode($productSales->jsonSerialize()));
    }

    /**
     * @throws WeekDoesNotExistException
     */
    public function testSelectProductSalesOfLastWeeksNegativeWeekCount(): void
    {
        $productSales = $this->dbm->selectProductSalesOfLastWeeks([1,2], -1, 1);
        assertSame("[]",json_encode($productSales->jsonSerialize()));
    }

    /**
     * @throws WeekDoesNotExistException
     * @throws Exception
     */
    public function testSelectProductSalesOfLastWeeksSameYear(): void
    {
        $purchaseId1 = $this->dbm->insertPurchase();
        $currentYear = (int) (new DateTime())->format("Y");
        $this->dbm->updatePurchase($purchaseId1, $this->getIsoDatetime($currentYear, 33));
        $this->dbm->insertPurchaseItem($purchaseId1, 1, 10, "3 Cookies", 0.69);
        $this->dbm->insertPurchaseItem($purchaseId1, 1, 5,  "3 Cookies", 0.69);
        $this->dbm->insertPurchaseItem($purchaseId1, 2, 3,  "3 Cookies", 0.69);

        $currentWeek = 34;
        $weekCount = 8;

        $productSales = $this->dbm->selectProductSalesOfLastWeeks([1, 2], $weekCount, $currentWeek);

        assertSame(15, $productSales->getQuantityByWeek(1, 33));

        assertSame(3, $productSales->getQuantityByWeek(2, 33, $currentYear));

        assertSame(
            '{"1":{"2025":{"26":{"quantity":0},"27":{"quantity":0},"28":{"quantity":0},"29":{"quantity":0},"30":{"quantity":0},"31":{"quantity":0},"32":{"quantity":0},"33":{"quantity":15},"34":{"quantity":0}}},"2":{"2025":{"26":{"quantity":0},"27":{"quantity":0},"28":{"quantity":0},"29":{"quantity":0},"30":{"quantity":0},"31":{"quantity":0},"32":{"quantity":0},"33":{"quantity":3},"34":{"quantity":0}}}}',
            json_encode($productSales->jsonSerialize())
        );
    }

    /**
     * @throws WeekDoesNotExistException
     * @throws Exception
     * Available from php8.3 throws DateInvalidOperationException
     */
    public function testSelectProductSalesOfLastWeeksNotSameYear(): void
    {
        $purchaseId1 = $this->dbm->insertPurchase();
        $previousYear = (int) (new DateTime())->sub(new DateInterval("P1Y"))->format("Y");
        $this->dbm->updatePurchase($purchaseId1, $this->getIsoDatetime($previousYear, 52));
        $this->dbm->insertPurchaseItem($purchaseId1, 1, 3, "3 Cookies", 0.69);

        $weekCount = 8;
        $currentWeek = 4;
        $productSales = $this->dbm->selectProductSalesOfLastWeeks([1], $weekCount, $currentWeek);
        assertSame(3, $productSales->getQuantityByWeek(1, 52, $previousYear));

        assertSame(
            '{"1":{"2024":{"49":{"quantity":0},"50":{"quantity":0},"51":{"quantity":0},"52":{"quantity":3}},"2025":{"1":{"quantity":0},"2":{"quantity":0},"3":{"quantity":0},"4":{"quantity":0}}}}',
            json_encode($productSales->jsonSerialize())
        );
    }

    private function getIsoDatetime(int $isoYear, int $isoWeek): DateTime
    {
        $dt = (new DateTime())->setISODate($isoYear, $isoWeek);
        $dt->setTime((int)substr('12:00:00', 0, 2), (int)substr('12:00:00', 3, 2), (int)substr('12:00:00', 6, 2));

        return $dt;
    }

    /**
     * @throws Exception
     */
    public function testSelectProductSalesByWeek(): void
    {
        $purchaseId1 = $this->dbm->insertPurchase();
        $currentYear = (int) (new DateTime())->format("Y");
        $this->dbm->updatePurchase($purchaseId1, $this->getIsoDatetime($currentYear, 33));
        $this->dbm->insertPurchaseItem($purchaseId1, 1, 10, "3 Cookies", 0.69);
        $this->dbm->insertPurchaseItem($purchaseId1, 1, 5,  "3 Cookies", 0.69);
        $this->dbm->insertPurchaseItem($purchaseId1, 2, 3,  "3 Cookies", 0.69);

        $week = 33;
        $productSales = $this->dbm->selectProductSalesByWeeks([1, 2], [$week]);

        assertSame(15, $productSales->getQuantityByWeek(1, $week));
        assertSame(3, $productSales->getQuantityByWeek(2, $week, $currentYear));
    }

    /**
     * @throws Throwable
     */
    public function testUpdateProductTable(): void
    {
        $products = [
            new Product(1, "Product 1", 99),
            new Product(2, "Product 2", 420),
            new Product(3, "Product 3"),
        ];
        $this->dbm->updateProductsTable($products);
        assertSame(1, $this->dbh->rowCount(
            'products', 'product_id = 1 AND product_name = "Product 1" AND unit_price = 0.99')
        );
        assertSame(1, $this->dbh->rowCount(
            'products', 'product_id = 2 AND product_name = "Product 2" AND unit_price = 4.20')
        );
        assertSame(1, $this->dbh->rowCount(
            'products', 'product_id = 3 AND product_name = "Product 3" AND unit_price = 0.00')
        );

        $products = [
            new Product(2, "Product 2", 67),
            new Product(4, "Product 4", 0),
        ];
        $this->dbm->updateProductsTable($products);
        assertSame(1, $this->dbh->rowCount(
            'products', 'product_id = 2 AND product_name = "Product 2" AND unit_price = 0.67')
        );
        assertSame(1, $this->dbh->rowCount(
            'products', 'product_id = 4 AND product_name = "Product 4" AND unit_price = 0.00')
        );
    }

    public function testGetProductImageException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product image 2 not found");

        $this->dbm->getProductImage(2);
    }

    public function testAddProductImage(): void
    {
        $createdId = $this->dbm->addProductImage(2, "/public/Candy/", "image", "jpg", 'image/jpeg');
        assertSame(2, $createdId);
        assertSame(1, $this->dbh->rowCount(
            'product_images', 'product_id = 2 AND path = "/public/Candy/" AND image_name = "image" AND extension = "jpg" AND mime_type = "image/jpeg"')
        );
    }

    public function testUpdateProductImage(): void
    {
        $createdId = $this->dbm->addProductImage(2, "/public/Candy/", "image", "jpg", 'image/jpeg');
        assertSame(2, $createdId);
        assertSame(1, $this->dbh->rowCount('product_images'));
        assertSame(1, $this->dbh->rowCount(
            'product_images', 'product_id = 2 AND path = "/public/Candy/" AND image_name = "image" AND extension = "jpg" AND mime_type = "image/jpeg"')
        );

        $updated = $this->dbm->updateProductImage(2, "/public/Cookies/", "image2", "png", 'image/png');
        assertTrue($updated);
        assertSame(1, $this->dbh->rowCount('product_images'));
        assertSame(1, $this->dbh->rowCount(
            'product_images', 'product_id = 2 AND path = "/public/Cookies/" AND image_name = "image2" AND extension = "png" AND mime_type = "image/png"')
        );
    }
}
