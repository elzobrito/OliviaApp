<?php

namespace OliviaPublico\Components;

abstract class Component
{
    protected $props = [];

    public function __construct(array $props = [])
    {
        $this->props = $props;
    }

    protected function prop(string $name, $default = null)
    {
        return array_key_exists($name, $this->props) ? $this->props[$name] : $default;
    }

    abstract public function render(): void;
}
