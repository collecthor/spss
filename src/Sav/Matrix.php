<?php
declare(strict_types=1);

namespace SPSS\Sav;

/**
 * Implements a more memory efficient 2 dimensional buffer for writing SPSS data files
 * @package SPSS\Sav
 */
class Matrix
{
    private \SplFixedArray $rows;

    public function __construct(int $rows, int $columns)
    {
        $this->rows = new \SplFixedArray($rows);
        for($i = 0; $i < $columns; $i++) {
            $this->rows[$i] = new \SplFixedArray($columns);
        }
    }

    public function set(int $row, int $column, $value): void
    {
        $this->rows[$row][$column] = $value;
    }

    public function get(int $row, int $column)
    {
        return $this->rows[$row][$column];
    }


}