<?php

namespace Arachnid;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

class QueryResult implements ArrayAccess, IteratorAggregate {
	/**
	 * Total available results.
	 *
	 * @var int
	 */
	public $total;

	/**
	 * List of Entry items.
	 *
	 * @var Entry[]
	 */
	public $items = [];

	/**
	 * Query used to generate the result.
	 *
	 * @var string|null
	 */
	public $query = null;

	/**
	 * Constructor.
	 *
	 * @param Entry[] $items Items from the query.
	 */
	public function __construct( $items ) {
		$this->items = $items;
	}

	/**
	 * Get the object iterator.
	 *
	 * @return Traversable
	 */
	public function getIterator() {
		return new ArrayIterator( $this->items );
	}

	/**
	 * Check if an offset exists.
	 *
	 * @param string|int Array offset to check.
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		return isset( $this->items[ $offset] );
	}

	/**
	 * Get an offset.
	 *
	 * @param string|int Array offset to get.
	 * @return mixed
	 */
	public function offsetGet( $offset ) {
		return $this->items[ $offset ];
	}

	/**
	 * Set an offset.
	 *
	 * @param string|int Array offset to set.
	 * @param mixed $value
	 */
	public function offsetSet( $offset, $value ) {
		$this->items[ $offset ] = $value;
	}

	/**
	 * Unset an offset.
	 *
	 * @param string|int Array offset to unset.
	 */
	public function offsetUnset( $offset ) {
		unset( $this->items[ $offset ] );
	}
}
