<?php

namespace Botkaplus;

class KeypadChat {
    private array $rows = [];
    private bool $resize_keyboard = true;
    private bool $on_time_keyboard = false;

    /**
     * افزودن یک ردیف جدید از دکمه‌ها
     *
     * @param array $buttons آرایه‌ای از دکمه‌ها
     * @return void
     */
    public function addRow(array $buttons): void {
        $this->rows[] = ["buttons" => $buttons];
    }

    /**
     * ساخت دکمه ساده
     *
     * @param string $id شناسه دکمه
     * @param string $text متن دکمه
     * @return array
     */
    public static function simpleButton(string $id, string $text): array {
        return [
            "id" => $id,
            "type" => "Simple",
            "button_text" => $text
        ];
    }

    public static function Button_NumberPicker(string $id, string $text): array {
        return [
            "id" => $id,
            "type" => "NumberPicker",
            "button_text" => $text
        ];
    }

    /**
     * تنظیم resize_keyboard
     *
     * @param bool $value
     * @return void
     */
    public function setResizeKeyboard(bool $value): void {
        $this->resize_keyboard = $value;
    }

    /**
     * تنظیم on_time_keyboard
     *
     * @param bool $value
     * @return void
     */
    public function setOnTimeKeyboard(bool $value): void {
        $this->on_time_keyboard = $value;
    }

    /**
     * گرفتن خروجی نهایی chat_keypad
     *
     * @return array
     */
    public function build(): array {
        return [
            "rows" => $this->rows,
            "resize_keyboard" => $this->resize_keyboard,
            "on_time_keyboard" => $this->on_time_keyboard
        ];
    }

}

?>
