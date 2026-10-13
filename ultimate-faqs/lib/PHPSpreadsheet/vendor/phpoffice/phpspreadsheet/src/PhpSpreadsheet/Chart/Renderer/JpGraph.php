<?php

namespace EwdUfaqVendor\PhpOffice\PhpSpreadsheet\Chart\Renderer;

/**
 * Jpgraph is not oficially maintained in Composer, so the version there
 * could be out of date. For that reason, all unit test requiring Jpgraph
 * are skipped. So, do not measure code coverage for this class till that
 * is fixed.
 *
 * This implementation uses abandoned package
 * https://packagist.org/packages/jpgraph/jpgraph
 *
 * @codeCoverageIgnore
 */
class JpGraph extends JpGraphRendererBase
{
    protected static function init() : void
    {
        static $loaded = \false;
        if ($loaded) {
            return;
        }
        // JpGraph is no longer included with distribution, but user may install it.
        // So Scrutinizer's complaint that it can't find it is reasonable, but unfixable.
        \EwdUfaqVendor\JpGraph\JpGraph::load();
        \EwdUfaqVendor\JpGraph\JpGraph::module('bar');
        \EwdUfaqVendor\JpGraph\JpGraph::module('contour');
        \EwdUfaqVendor\JpGraph\JpGraph::module('line');
        \EwdUfaqVendor\JpGraph\JpGraph::module('pie');
        \EwdUfaqVendor\JpGraph\JpGraph::module('pie3d');
        \EwdUfaqVendor\JpGraph\JpGraph::module('radar');
        \EwdUfaqVendor\JpGraph\JpGraph::module('regstat');
        \EwdUfaqVendor\JpGraph\JpGraph::module('scatter');
        \EwdUfaqVendor\JpGraph\JpGraph::module('stock');
        $loaded = \true;
    }
}
