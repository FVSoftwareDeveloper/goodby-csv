<?php

namespace Goodby\CSV\Export\Standard;

use Goodby\CSV\Export\Protocol\ExporterInterface;
use Goodby\CSV\Export\Protocol\Exception\IOException;
use Goodby\CSV\Export\Standard\Exception\StrictViolationException;

/**
 * Standard exporter class
 */
class Exporter implements ExporterInterface
{
    /**
     * @var ExporterConfig
     */
    private $config;

    /**
     * @var int
     */
    private $rowConsistency = null;

    /**
     * @var bool
     */
    private $strict = true;

    /**
     * Return new Exporter object
     * @param ExporterConfig $config
     */
    public function __construct(ExporterConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Disable strict mode
     */
    public function unstrict()
    {
        $this->strict = false;
    }

    /**
     * {@inherit}
     * @throws StrictViolationException
     */
    public function export($filename, $rows)
    {
        $delimiter     = $this->config->getDelimiter();
        $enclosure     = $this->config->getEnclosure();
        $enclosure     = empty($enclosure) ? "\0" : $enclosure;
        $newline       = $this->config->getNewline();
        $fromCharset   = $this->config->getFromCharset();
        $toCharset     = $this->config->getToCharset();
        $fileMode      = $this->config->getFileMode();
        $columnHeaders = $this->config->getColumnHeaders();

        try {
            $csv = new CsvFileObject($filename, $fileMode);
        } catch ( \Exception $e ) {
            throw new IOException($e->getMessage(), 0, $e);
        }

        $csv->setNewline($newline);

        if ( $toCharset ) {
            $csv->setCsvFilter(function($line) use($toCharset, $fromCharset) {
                return mb_convert_encoding($line, $toCharset, $fromCharset);
            });
        }

        if (count($columnHeaders) > 0) {
            $this->checkRowConsistency($columnHeaders);
            $csv->fputcsv($columnHeaders, $delimiter, $enclosure);
        }

        foreach ( $rows as $row ) {
            $this->checkRowConsistency($row);
            $csv->fputcsv($row, $delimiter, $enclosure);
        }
        $csv->fflush();
    }

    /**
     * Check if the column count is consistent with comparing other rows
     * @param array|\Countable $row
     * @throws Exception\StrictViolationException
     */
    private function checkRowConsistency($row)
    {
        if ( $this->strict === false ) {
            return;
        }

        $current = count($row);

        if ( $this->rowConsistency === null ) {
            $this->rowConsistency = $current;
        }

        if ( $current !== $this->rowConsistency ) {
            throw new StrictViolationException();
        }

        $this->rowConsistency = $current;
    }
}
