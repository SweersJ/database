<?php

namespace Compucie\Database\Sale\Model;

use function PHPUnit\Framework\stringEndsWith;

readonly class ProductImage
{

    public function __construct(
        private int $productId,
        private string $path,
        private string $imageName,
        private string $extension,
        private string $mimeType
    )
    {
    }
    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getImageName(): string
    {
        return $this->imageName;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getUrl(): string
    {
        $path = str_ends_with($this->path, '/') ? $this->path : $this->path . '/';
        return $path . $this->imageName . '.' . $this->extension;
    }
}