<?php

namespace elzobrito;

class Pagination
{
    private $_perPage;
    private $_instance;
    private $_page;
    private $_totalRows = 0;
    private $_customCSS;


    public function __construct($perPage, $instance, $customCSS = '')
    {
        $this->_instance = $instance;
        $this->_perPage = $perPage;
        $this->set_instance();
        $this->_customCSS = $customCSS;
    }

    public function get_start()
    {
        return ($this->_page * $this->_perPage) - $this->_perPage;
    }

    private function set_instance()
    {
        $this->_page = (int) filter_input(INPUT_GET, $this->_instance);
        $this->_page = ($this->_page == 0 ? 1 : ($this->_page < 0 ? 1 : $this->_page));
    }

    public function set_total($_totalRows)
    {
        $this->_totalRows = $_totalRows;
    }

    public function get_total()
    {
        return $this->_totalRows;
    }

    public function get_total_perPage()
    {
        return $this->_perPage;
    }

    public function get_limit()
    {
        return $this->get_start() . ",$this->_perPage";
    }

    public function get_limit_keys()
    {
        return ['offset' => $this->get_start(), 'limit' => $this->_perPage];
    }

    public function page_links($path = '?', $filter = null)
    {
        $path .= '?';
        $ext = null;
        if ($filter != null)
            $ext = '&' . http_build_query($filter);

        $adjacents = "2";
        $prev = $this->_page - 1;
        $next = $this->_page + 1;
        $lastpage = ceil($this->_totalRows / $this->_perPage) - 1;
        $lpm1 = $lastpage - 1;

        $pagination = "";


        $de = ($this->_page * $this->get_total_perPage());
        $ate = ($this->get_total() - $this->get_total_perPage());

        $information = 'Mostrando ' . ($this->_page == 1 ? $this->_page : ($this->_page * $this->get_total_perPage()) - $this->get_total_perPage() + 1) . ' ao ' . ($de > $ate ? $ate : $de) . ' de ' . ($this->get_total() - $this->get_total_perPage());

        if ($lastpage > 1) {
            $pagination .= "<div class='btn-group " . $this->_customCSS . "' role='group' aria-label='Paginacao'>";
            if ($this->_page > 1)
                $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$prev" . "$ext' class='text-white'>Anterior</a></button>";
            else
                $pagination .= "<button type='button' class='btn btn-secondary'>Anterior</button>";

            if ($lastpage < 7 + ($adjacents * 2)) {
                for ($counter = 1; $counter <= $lastpage; $counter++) {
                    if ($counter == $this->_page)
                        $pagination .= "<button type='button' class='btn btn-warning'>$counter</button>";
                    else
                        $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$counter" . "$ext' class='text-white'>$counter</a></button>";
                }
            } elseif ($lastpage > 5 + ($adjacents * 2)) {
                if ($this->_page < 1 + ($adjacents * 2)) {
                    for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++) {
                        if ($counter == $this->_page)
                            $pagination .= "<button type='button' class='btn btn-warning'>$counter</button>";
                        else
                            $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$counter" . "$ext' class='text-white'>$counter</a></button>";
                    }
                    $pagination .= "<button class='btn btn-warning current'>...</button>";
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$lpm1" . "$ext' class='text-white'>$lpm1</a></button>";
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$lastpage" . "$ext' class='text-white'>$lastpage</a></button>";
                } elseif ($lastpage - ($adjacents * 2) > $this->_page && $this->_page > ($adjacents * 2)) {
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=1" . "$ext' class='text-white'>1</a></button>";
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=2" . "$ext' class='text-white'>2</a></button>";
                    $pagination .= "<button class='btn btn-warning current'>...</button>";
                    for ($counter = $this->_page - $adjacents; $counter <= $this->_page + $adjacents; $counter++) {
                        if ($counter == $this->_page)
                            $pagination .= "<button type='button' class='btn btn-warning'>$counter</button>";
                        else
                            $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$counter" . "$ext' class='text-white'>$counter</a></button>";
                    }
                    $pagination .= "<button class='btn btn-warning current'>..</button>";
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$lpm1" . "$ext' class='text-white'>$lpm1</a></button>";
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$lastpage" . "$ext' class='text-white'>$lastpage</a></button>";
                } else {
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=1" . "$ext' class='text-white'>1</a></button>";
                    $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=2" . "$ext' class='text-white'>2</a></button>";
                    $pagination .= "<button class='btn btn-warning current'>...</button>";
                    for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++) {
                        if ($counter == $this->_page)
                            $pagination .= "<button class='btn btn-warning current'>$counter</button>";
                        else
                            $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$counter" . "$ext' class='text-white'>$counter</a></button>";
                    }
                }
            }

            if ($this->_page < $counter - 1)
                $pagination .= "<button type='button' class='btn btn-primary'><a href='" . $path . "$this->_instance=$next" . "$ext' class='text-white'>Próximo</a></button>";
            else
                $pagination .= "<button type='button' class='btn btn-primary'><span class='disabled'>Próximo</span></button>";
            $pagination .= "</div>";
        }

        $container = '<div class="container">
        <div class="row">
          <div class="col-3">
            ' . $information . '
          </div>
          <div class="col-9">
            ' . $pagination . '
          </div>
        </div>
      </div>';

        return $container;
    }
}
