<?php

namespace OliviaPagination;

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
        $this->_perPage = $this->normalizePositiveInt($perPage, 1);
        $this->set_instance();
        $this->_customCSS = $customCSS;
    }

    public function get_start()
    {
        return $this->getOffset();
    }

    private function set_instance()
    {
        $this->_page = $this->detectPageFromRequest();
    }

    public function set_total($_totalRows)
    {
        $this->_totalRows = max(0, (int) $_totalRows);
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
        $adjacents = 2;
        $prev = $this->_page - 1;
        $next = $this->_page + 1;
        $lastpage = $this->getTotalPages();
        $lpm1 = $lastpage - 1;

        $pagination = "";

        $information = $this->buildInformationText();

        if ($lastpage > 1) {
            $pagination .= "<div class='btn-group " . $this->escapeAttr($this->_customCSS) . "' role='group' aria-label='Paginacao'>";
            if ($this->_page > 1)
                $pagination .= $this->renderLinkButton($this->buildPageUrl($prev, $path, $filter), 'Anterior');
            else
                $pagination .= "<button type='button' class='btn btn-secondary'>Anterior</button>";

            if ($lastpage < 7 + ($adjacents * 2)) {
                for ($counter = 1; $counter <= $lastpage; $counter++) {
                    if ($counter == $this->_page)
                        $pagination .= "<button type='button' class='btn btn-warning'>$counter</button>";
                    else
                        $pagination .= $this->renderLinkButton($this->buildPageUrl($counter, $path, $filter), $counter);
                }
            } elseif ($lastpage > 5 + ($adjacents * 2)) {
                if ($this->_page < 1 + ($adjacents * 2)) {
                    for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++) {
                        if ($counter == $this->_page)
                            $pagination .= "<button type='button' class='btn btn-warning'>$counter</button>";
                        else
                            $pagination .= $this->renderLinkButton($this->buildPageUrl($counter, $path, $filter), $counter);
                    }
                    $pagination .= "<button class='btn btn-warning current'>...</button>";
                    $pagination .= $this->renderLinkButton($this->buildPageUrl($lpm1, $path, $filter), $lpm1);
                    $pagination .= $this->renderLinkButton($this->buildPageUrl($lastpage, $path, $filter), $lastpage);
                } elseif ($lastpage - ($adjacents * 2) > $this->_page && $this->_page > ($adjacents * 2)) {
                    $pagination .= $this->renderLinkButton($this->buildPageUrl(1, $path, $filter), 1);
                    $pagination .= $this->renderLinkButton($this->buildPageUrl(2, $path, $filter), 2);
                    $pagination .= "<button class='btn btn-warning current'>...</button>";
                    for ($counter = $this->_page - $adjacents; $counter <= $this->_page + $adjacents; $counter++) {
                        if ($counter == $this->_page)
                            $pagination .= "<button type='button' class='btn btn-warning'>$counter</button>";
                        else
                            $pagination .= $this->renderLinkButton($this->buildPageUrl($counter, $path, $filter), $counter);
                    }
                    $pagination .= "<button class='btn btn-warning current'>..</button>";
                    $pagination .= $this->renderLinkButton($this->buildPageUrl($lpm1, $path, $filter), $lpm1);
                    $pagination .= $this->renderLinkButton($this->buildPageUrl($lastpage, $path, $filter), $lastpage);
                } else {
                    $pagination .= $this->renderLinkButton($this->buildPageUrl(1, $path, $filter), 1);
                    $pagination .= $this->renderLinkButton($this->buildPageUrl(2, $path, $filter), 2);
                    $pagination .= "<button class='btn btn-warning current'>...</button>";
                    for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++) {
                        if ($counter == $this->_page)
                            $pagination .= "<button class='btn btn-warning current'>$counter</button>";
                        else
                            $pagination .= $this->renderLinkButton($this->buildPageUrl($counter, $path, $filter), $counter);
                    }
                }
            }

            if ($this->_page < $lastpage)
                $pagination .= $this->renderLinkButton($this->buildPageUrl($next, $path, $filter), 'Próximo');
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

    public function get_current_page()
    {
        return $this->_page;
    }

    public function get_total_pages()
    {
        return $this->getTotalPages();
    }

    public function has_previous_page()
    {
        return $this->_page > 1;
    }

    public function has_next_page()
    {
        return $this->_page < $this->getTotalPages();
    }

    public function set_current_page($page)
    {
        $this->_page = $this->normalizePositiveInt($page, 1);

        return $this;
    }

    public function to_array()
    {
        return [
            'current_page' => $this->get_current_page(),
            'per_page' => $this->get_total_perPage(),
            'total_rows' => $this->get_total(),
            'total_pages' => $this->get_total_pages(),
            'offset' => $this->get_start(),
            'limit' => $this->get_total_perPage(),
            'has_previous_page' => $this->has_previous_page(),
            'has_next_page' => $this->has_next_page(),
        ];
    }

    private function normalizePositiveInt($value, $default)
    {
        $value = (int) $value;

        return $value > 0 ? $value : $default;
    }

    private function detectPageFromRequest()
    {
        $page = filter_input(INPUT_GET, $this->_instance, FILTER_VALIDATE_INT);

        if ($page === null || $page === false) {
            return 1;
        }

        return $this->normalizePositiveInt($page, 1);
    }

    private function getOffset()
    {
        return ($this->_page - 1) * $this->_perPage;
    }

    private function getTotalPages()
    {
        if ($this->_totalRows === 0) {
            return 1;
        }

        return (int) ceil($this->_totalRows / $this->_perPage);
    }

    private function getCurrentPageStartRecord()
    {
        if ($this->_totalRows === 0) {
            return 0;
        }

        return $this->getOffset() + 1;
    }

    private function getCurrentPageEndRecord()
    {
        if ($this->_totalRows === 0) {
            return 0;
        }

        return min($this->_totalRows, $this->_page * $this->_perPage);
    }

    private function buildInformationText()
    {
        return 'Mostrando ' . $this->getCurrentPageStartRecord() . ' ao ' . $this->getCurrentPageEndRecord() . ' de ' . $this->get_total();
    }

    private function buildPageUrl($page, $path, $filter)
    {
        $path = (string) $path;

        if ($path === '' || $path === '?') {
            $path = '';
        }

        $query = [];

        if (is_array($filter)) {
            $query = $filter;
        }

        $query[$this->_instance] = (int) $page;

        $separator = strpos($path, '?') === false ? '?' : '&';

        return $path . $separator . http_build_query($query);
    }

    private function renderLinkButton($url, $label)
    {
        return "<a href='" . $this->escapeAttr($url) . "' class='btn btn-primary text-white'>" . $this->escapeHtml($label) . "</a>";
    }

    private function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function escapeAttr($value)
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }
}
