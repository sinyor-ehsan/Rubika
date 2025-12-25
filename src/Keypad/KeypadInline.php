<?php
namespace Botkaplus;

class KeypadInline {
    private $rows = [];

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

    /**
     * ساخت دکمه لینک‌دار
     *
     * @param string $id شناسه دکمه
     * @param string $text متن دکمه
     * @param string $url آدرس لینک
     * @return array
     */
    public static function linkButton(string $id, string $text, string $url): array {
        return [
            "id" => $id,
            "type" => "Link",
            "button_text" => $text,
            "button_link" => ["type" => "url", "link_url" => $url]
        ];
    }

    public static function Button_joinchannel_data(string $id, string $text, string $username, $ask_join = true): array {
        return [
            "id" => $id,
            "type" => "Link",
            "button_text" => $text,
            "button_link" => ["type" => "joinchannel", "joinchannel_data" => ["username" => $username, "ask_join" => $ask_join]]
        ];
    }

    /**
     * گرفتن خروجی نهایی inline_keypad
     *
     * @return array
     */
    public function build(): array {
        return ["rows" => $this->rows];
    }

}

?>
