<?php

namespace MoMagdy\TelegramSupport\View\Components;

use Illuminate\View\Component;

class TelegramWidget extends Component
{
    public function __construct(
        public string $title = '',
        public string $color = '#2196F3',
    ) {
        if (! $this->title) {
            $this->title = __('telegram-support::telegram.widget_title');
        }
    }

    public function render()
    {
        return view('telegram-support::components.telegram-widget');
    }
}
