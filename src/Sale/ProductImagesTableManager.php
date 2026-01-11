<?php

namespace Compucie\Database\Sale;

use Compucie\Database\Sale\Model\ProductImage;
use Exception;
use mysqli;
use mysqli_sql_exception;

trait ProductImagesTableManager
{
    protected abstract function getClient(): mysqli;

    /**
     * @throws  mysqli_sql_exception
     */
    public function createProductImagesTable(): void
    {
        $statement = $this->getClient()->prepare(
            "CREATE TABLE IF NOT EXISTS `product_images` (
                `product_id` INT NOT NULL UNIQUE AUTO_INCREMENT,
                `path` VARCHAR(255) DEFAULT NULL,
                `image_name` VARCHAR(255) DEFAULT NULL,
                `extension` VARCHAR(255) DEFAULT NULL,
                `mime_type` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`product_id`)
            );"
        );
        if ($statement){
            $statement->execute();
            $statement->close();
        }
    }

    public function addProductImage(
        int $productId,
        string $path,
        string $imageName,
        string $extension,
        string $mimeType
    ): int {
        return $this->executeCreate(
            'product_images',
            ['`product_id`', '`path`', '`image_name`', '`extension`', '`mime_type`'],
            [$productId, $path, $imageName, $extension, $mimeType],
            'issss'
        );
    }

    /**
     * @throws Exception
     * @throws  mysqli_sql_exception
     */
    public function getProductImage(int $productId): ?ProductImage
    {
        if ($productId <= 0){
            return null;
        }

        $row = $this->executeReadOne(
            "SELECT * 
            FROM `product_images` 
            WHERE `productId` = ?",
            [$productId],
            "i"
        );

        if ($row === null){
            throw new Exception("Product image $productId not found");
        }

        return new ProductImage(
            (int) $row['product_id'],
            (string) $row['path'],
            (string) $row['image_name'],
            (string) $row['extension'],
            (string) $row['mime_type']
        );
    }

    public function updateProductImage(
        int $productId,
        string $path,
        string $imageName,
        string $extension,
        string $mimeType
    ): bool {
        return $this->executeUpdate(
            'product_images',
            'product_id',
            $productId,
            [
                '`path` = ?',
                '`image_name` = ?',
                '`extension` = ?',
                '`mime_type` = ?'
            ],
            [$path, $imageName, $extension, $mimeType],
            'ss'
        );
    }
}