<?php
/**
 * PDF renderer contract.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a Document into PDF bytes.
 *
 * Keeping this behind an interface means the engine can be replaced without
 * touching the documents or templates.
 */
interface Renderer {

	/**
	 * Whether the underlying engine is installed.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Human-readable engine name, for error messages.
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * Render a document to PDF bytes.
	 *
	 * @param Document $document Document to render.
	 *
	 * @return string Raw PDF bytes.
	 *
	 * @throws Render_Exception When rendering fails.
	 */
	public function render( Document $document ): string;
}
