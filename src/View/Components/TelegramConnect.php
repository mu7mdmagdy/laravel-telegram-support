<?php

namespace MoMagdy\TelegramSupport\View\Components;

use Illuminate\View\Component;

class TelegramConnect extends Component
{
    public function __construct(
        public string $label    = '',
        public string $color    = '#3D94E7',
        public bool   $required = false,
    ) {
        if (! $this->label) {
            $this->label = __('telegram-support::telegram.connect_label');
        }
    }

    public function render()
    {
        return view('telegram-support::components.telegram-connect');
    }
}
