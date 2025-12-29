<?php

namespace Botkaplus;

require_once 'InlineKeypad.php';

class ChatKeypad extends InlineKeypad
{
    private bool $resize_keyboard = true;
    private bool $on_time_keyboard = false;

    public function setResizeKeyboard(bool $value): void {
        $this->resize_keyboard = $value;
    }

    public function setOnTimeKeyboard(bool $value): void {
        $this->on_time_keyboard = $value;
    }

    public function build(): array
    {

        $data = parent::build();

        $data["resize_keyboard"] = $this->resize_keyboard;
        $data["on_time_keyboard"] = $this->on_time_keyboard;

        return $data;
    }
}

?>
