<?php
/**
 * Canonical map object.
 *
 * @package MiniFAIR
 */

// phpcs:disable HM.Files.NamespaceDirectoryName.NameMismatch -- Avoids a bug which detects strict_types as the namespace.

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
class CanonicalMapObject extends AbstractCBORObject implements Countable, IteratorAggregate, Normalizable, ArrayAccess {

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
	public function __construct( array $data = [] ) {
		[$additional_information, $length] = LengthCalculator::getLengthOfArray( $data );
		array_map(static function ( $item ): void {
			if ( ! $item instanceof MapItem ) {
				throw new InvalidArgumentException( 'The list must contain only MapItem objects.' );
			}
		}, $data);

		parent::__construct( self::MAJOR_TYPE, $additional_information );
		$this->data = $data;
		$this->length = $length;
	}

	/**
	 * Return a string representation of the object.
	 *
	 * @return string
	 */
	public function __toString(): string {
		usort($this->data, function ( $a, $b ) {
			$a_key = (string) $a->getKey();
			$b_key = (string) $b->getKey();
			if ( strlen( $a_key ) === strlen( $b_key ) ) {
				return strcmp( $a_key, $b_key );
			}

			return strlen( $a_key ) <=> strlen( $b_key );
		});

		$result = parent::__toString();
		if ( $this->length !== null ) {
			$result .= $this->length;
		}
		foreach ( $this->data as $object ) {
			$result .= $object->getKey()
				->__toString();
			$result .= $object->getValue()
				->__toString();
		}

		return $result;
	}

	/**
	 * Create the object.
	 *
	 * @param MapItem[] $data Optional. The map data.
	 * @return self
	 */
	public static function create( array $data = [] ): self {
		return new self( $data );
	}

	/**
	 * Add an item to the map.
	 *
	 * @throws InvalidArgumentException If the key is not normalizable.
	 * @param CBORObject $key   The map key.
	 * @param CBORObject $value The value.
	 * @return self
	 */
	public function add( CBORObject $key, CBORObject $value ): self {
		if ( ! $key instanceof Normalizable ) {
			throw new InvalidArgumentException( 'Invalid key. Shall be normalizable' );
		}
		$this->data[ $key->normalize() ] = MapItem::create( $key, $value );
		[$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray( $this->data );

		return $this;
	}

	/**
	 * Check if the key exists.
	 *
	 * @param int|string $key The key.
	 * @return bool Whether the key exists.
	 */
	public function has( int|string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * Remove an item.
	 *
	 * @param int|string $index The key.
	 * @return self
	 */
	public function remove( int|string $index ): self {
		if ( ! $this->has( $index ) ) {
			return $this;
		}
		unset( $this->data[ $index ] );
		$this->data = array_values( $this->data );
		[$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray( $this->data );

		return $this;
	}

	/**
	 * Get an item.
	 *
	 * @throws InvalidArgumentException If the key does not exist.
	 * @param int|string $index The key.
	 * @return CBORObject The item.
	 */
	public function get( int|string $index ): CBORObject {
		if ( ! $this->has( $index ) ) {
			throw new InvalidArgumentException( 'Index not found.' );
		}

		return $this->data[ $index ]->getValue();
	}

	/**
	 * Set an item.
	 *
	 * @throws InvalidArgumentException If the key is not normalizable.
	 * @param MapItem $object The object to set.
	 * @return self
	 */
	public function set( MapItem $object ): self {
		$key = $object->getKey();
		if ( ! $key instanceof Normalizable ) {
			throw new InvalidArgumentException( 'Invalid key. Shall be normalizable' );
		}

		$this->data[ $key->normalize() ] = $object;
		[$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray( $this->data );

		return $this;
	}

	/**
	 * Get the number of items.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->data );
	}

	/**
	 * Get an iterator of the map data.
	 *
	 * @return Iterator<int, MapItem>
	 */
	public function getIterator(): Iterator {
		return new ArrayIterator( $this->data );
	}

	/**
	 * Get normalized map data.
	 *
	 * @throws InvalidArgumentException If the key is not normalizable.
	 * @return array<int|string, mixed>
	 */
	public function normalize(): array {
		return array_reduce($this->data, static function ( array $carry, MapItem $item ): array {
			$key = $item->getKey();
			if ( ! $key instanceof Normalizable ) {
				throw new InvalidArgumentException( 'Invalid key. Shall be normalizable' );
			}

			$value_object = $item->getValue();
			$carry[ $key->normalize() ] = $value_object instanceof Normalizable ? $value_object->normalize() : $value_object;

			return $carry;
		}, []);
	}

	/**
	 * Check if the key exists.
	 *
	 * @param int|string $offset The key.
	 * @return bool
	 */
	public function offsetExists( $offset ): bool {
		return $this->has( $offset );
	}

	/**
	 * Get an item.
	 *
	 * @param int|string $offset The key.
	 * @return CBORObject The item.
	 */
	public function offsetGet( $offset ): CBORObject {
		return $this->get( $offset );
	}

	/**
	 * Set an item.
	 *
	 * @throws InvalidArgumentException If the key is not an instance of CBORObject.
	 * @throws InvalidArgumentException If the value is not an instance of CBORObject.
	 * @param CBORObject $offset The key.
	 * @param CBORObject $value  The value.
	 * @return void
	 */
	public function offsetSet( $offset, $value ): void {
		if ( ! $offset instanceof CBORObject ) {
			throw new InvalidArgumentException( 'Invalid key' );
		}
		if ( ! $value instanceof CBORObject ) {
			throw new InvalidArgumentException( 'Invalid value' );
		}

		$this->set( MapItem::create( $offset, $value ) );
	}

	/**
	 * Remove an item.
	 *
	 * @param int|string $offset The key.
	 * @return void
	 */
	public function offsetUnset( $offset ): void {
		$this->remove( $offset );
	}
}
