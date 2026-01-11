<?php

namespace Compucie\Database\Sale;

use Compucie\Database\DatabaseManager;

class SaleDatabaseManager extends DatabaseManager
{
    use PurchasesTableManager;
    use PurchaseItemsTableManager;
    use ProductsTableManager;
    use ProductImagesTableManager;

    public function createTables(): void
    {
        $this->createPurchasesTable();
        $this->createPurchaseItemsTable();
        $this->createProductsTable();
        $this->createProductImagesTable();
    }
}
