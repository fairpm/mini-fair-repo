<?php
/**
 * Canonical map object.
 *
 * @package MiniFAIR
 */

declare(strict_types=1);

namespace MiniFAIR\PLC;

use ArrayAccess;
use ArrayIterator;
use CBOR\{
	AbstractCBORObject,
	CBORObject,
	LengthCalculator,
	MapItem,
	Normalizable
};
use Countable;
use function array_key_exists;
use function count;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;

/**
 * MapObject, implementing the canonicalization algorithm.
 *
 * This implements section 3.9 canonicalization, i.e. sort keys by length, then
 * by lower (byte) value.
 *
 * (The main change here is in ->__toString())
 *
 * @internal Unfortunately, MapObject is Final, so we need to duplicate the code.
 *
 * @phpstan-implements ArrayAccess<int, CBORObject>
 * @phpstan-implements IteratorAggregate<int, MapItem>
 */
class CanonicalMapObject extends AbstractCBORObject implements Countable, IteratorAggregate, Normalizable, ArrayAccess
{
    private const MAJOR_TYPE = self::MAJOR_TYPE_MAP;

	/**
	 * The map data.
	 *
	 * @var MapItem[]
	 */
	private array $data;

	/**
	 * The data's length.
	 *
	 * @var ?string
	 */
	private ?string $length = null;

	/**
	 * Constructor.
	 *
	 * @param MapItem[] $data The data for the map.
	 * @return void
	 */

        parent::__construct(self::MAJOR_TYPE, $additionalInformation);
        $this->data = $data;
        $this->length = $length;
    }

	/**
	 * Return a string representation of the object.
	 *
	 * @return string
	 */

            return strlen($a_key) <=> strlen($b_key);
        });

        $result = parent::__toString();
        if ($this->length !== null) {
            $result .= $this->length;
        }
        foreach ($this->data as $object) {
            $result .= $object->getKey()
                ->__toString()
            ;
            $result .= $object->getValue()
                ->__toString()
            ;
        }

        return $result;
    }

	/**
	 * Create the object.
	 *
	 * @param MapItem[] $data Optional. The map data.
	 * @return self
	 */
	/**
	 * Add an item to the map.
	 *
	 * @throws InvalidArgumentException If the key is not normalizable.
	 * @param CBORObject $key   The map key.
	 * @param CBORObject $value The value.
	 * @return self
	 */

        return $this;
    }

	/**
	 * Check if the key exists.
	 *
	 * @param int|string $key The key.
	 * @return bool Whether the key exists.
	 */
	/**
	 * Remove an item.
	 *
	 * @param int|string $index The key.
	 * @return self
	 */

        return $this;
    }

	/**
	 * Get an item.
	 *
	 * @throws InvalidArgumentException If the key does not exist.
	 * @param int|string $index The key.
	 * @return CBORObject The item.
	 */
	/**
	 * Set an item.
	 *
	 * @throws InvalidArgumentException If the key is not normalizable.
	 * @param MapItem $object The object to set.
	 * @return self
	 */

        return $this;
    }

	/**
	 * Get the number of items.
	 *
	 * @return int
	 */

	/**
	 * Get an iterator of the map data.
	 *
	 * @return Iterator<int, MapItem>
	 */

	/**
	 * Get normalized map data.
	 *
	 * @throws InvalidArgumentException If the key is not normalizable.
	 * @return array<int|string, mixed>
	 */

            return $carry;
        }, []);
    }

	/**
	 * Check if the key exists.
	 *
	 * @param int|string $offset The key.
	 * @return bool
	 */
	/**
	 * Get an item.
	 *
	 * @param int|string $offset The key.
	 * @return CBORObject The item.
	 */
	/**
	 * Set an item.
	 *
	 * @throws InvalidArgumentException If the key is not an instance of CBORObject.
	 * @throws InvalidArgumentException If the value is not an instance of CBORObject.
	 * @param CBORObject $offset The key.
	 * @param CBORObject $value  The value.
	 * @return void
	 */
	/**
	 * Remove an item.
	 *
	 * @param int|string $offset The key.
	 * @return void
	 */
}
