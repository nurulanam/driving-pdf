<?php
/**
 * Rendering failure.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a document cannot be rendered or stored.
 */
final class Render_Exception extends \RuntimeException {
}
