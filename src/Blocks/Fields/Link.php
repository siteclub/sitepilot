<?php

namespace Sitepilot\Blocks\Fields;

class Link extends Field
{
    /**
     * Returns the field's ACF config.
     *
     * @return array
     */
    protected function get_acf_config(string $namespace): array
    {
        return [
            'type' => 'url'
        ];
    }
}