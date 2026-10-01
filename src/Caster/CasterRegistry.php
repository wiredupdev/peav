<?php

declare(strict_types=1);

/*
 * This file is part of the Peav package.
 *
 * (c) WireUpDev <wireupdev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WireUpDev\Peav\Caster;

use WireUpDev\Peav\Exception\CasterNotFoundException;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeInterface;

/**
 * Registry and manager for built-in and user-defined EAV attribute type casters.
 */
class CasterRegistry
{
    /**
     * @var array<string, TypeCasterInterface>
     */
    private array $casters = [];

    public function __construct()
    {
        $this->registerBuiltinCasters();
    }

    /**
     * Registers a type caster for a storage bucket or custom type name.
     */
    public function register(StorageBucket|string $bucketOrType, TypeCasterInterface $caster): self
    {
        $key = $bucketOrType instanceof StorageBucket ? $bucketOrType->value : $bucketOrType;
        $this->casters[$key] = $caster;

        return $this;
    }

    /**
     * Checks if a caster is registered for a storage bucket or custom type name.
     */
    public function has(StorageBucket|string $bucketOrType): bool
    {
        $key = $bucketOrType instanceof StorageBucket ? $bucketOrType->value : $bucketOrType;

        return isset($this->casters[$key]);
    }

    /**
     * Retrieves a registered type caster by storage bucket or type name.
     *
     * @throws CasterNotFoundException
     */
    public function get(StorageBucket|string $bucketOrType): TypeCasterInterface
    {
        $key = $bucketOrType instanceof StorageBucket ? $bucketOrType->value : $bucketOrType;

        if (!isset($this->casters[$key])) {
            throw new CasterNotFoundException(sprintf('Type caster for "%s" is not registered in CasterRegistry.', $key));
        }

        return $this->casters[$key];
    }

    /**
     * Resolves the appropriate caster for a given TypeInterface instance.
     * Prioritizes type-specific casters before falling back to storage bucket casters.
     *
     * @throws CasterNotFoundException
     */
    public function getForType(TypeInterface $type): TypeCasterInterface
    {
        if ($this->has($type->getName())) {
            return $this->get($type->getName());
        }

        return $this->getForBucket($type->getStorageBucket());
    }

    /**
     * Retrieves the caster configured for a physical storage bucket.
     *
     * @throws CasterNotFoundException
     */
    public function getForBucket(StorageBucket $bucket): TypeCasterInterface
    {
        return $this->get($bucket);
    }

    /**
     * Returns all registered type casters.
     *
     * @return array<string, TypeCasterInterface>
     */
    public function all(): array
    {
        return $this->casters;
    }

    private function registerBuiltinCasters(): void
    {
        $this->casters[StorageBucket::String->value] = new StringTypeCaster();
        $this->casters[StorageBucket::Integer->value] = new IntegerTypeCaster();
        $this->casters[StorageBucket::Decimal->value] = new DecimalTypeCaster();
        $this->casters[StorageBucket::DateTime->value] = new DateTimeTypeCaster();
        $this->casters[StorageBucket::Boolean->value] = new BooleanTypeCaster();
        $this->casters[StorageBucket::Text->value] = new TextTypeCaster();
        $this->casters[StorageBucket::Json->value] = new JsonTypeCaster();
        $this->casters[StorageBucket::Blob->value] = new BlobTypeCaster();
    }
}
