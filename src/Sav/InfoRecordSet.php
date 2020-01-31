<?php
declare(strict_types=1);

namespace SPSS\Sav;

/**
 * Class InfoRecordSet
 * Models the info records in a SAV file
 * @package SPSS\Sav
 */
class InfoRecordSet
{
    private $records = [];

    public function set(Record\Info $info)
    {
        $this->records[$info::SUBTYPE] = $info;
    }

}