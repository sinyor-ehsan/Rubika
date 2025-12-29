<?php
namespace Botkaplus;

class InlineKeypad {
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
    public static function buttonSimple(string $id, string $text): array {
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
    public static function buttonUrlLink(string $id, string $text, string $url): array {
        return [
            "id" => $id,
            "type" => "Link",
            "button_text" => $text,
            "button_link" => ["type" => "url", "link_url" => $url]
        ];
    }

    public static function buttonJoinChannelData(
        string $id,
        string $text,
        string $username,
        bool $ask_join = true
    ): array {
        return [
            "id" => $id,
            "type" => "Link",
            "button_text" => $text,
            "button_link" => [
                "type" => "joinchannel",
                "joinchannel_data" => [
                    "username" => str_replace("@", "", $username),
                    "ask_join" => $ask_join
                ]
            ]
        ];
    }

    public static function buttonSelection(
        string $id,
        string $text,
        string $selection_id,
        string $title,
        array $items,
        string $search_type = "None",
        string $get_type = "Local",
        int $columns_count = 1,
        bool $is_multi_selection = false
    ): array {
        return [
            "id" => $id,
            "type" => "Selection",
            "button_text" => $text,
            "button_selection" => [
                "selection_id" => $selection_id,
                "title" => $title,
                "search_type" => $search_type,
                "get_type" => $get_type,
                "columns_count" => (string)$columns_count,
                "is_multi_selection" => $is_multi_selection,
                "items" => $items
            ]
        ];
    }

    public static function selectionItem(
        string $text,
        string $image_url,
        string $type = "TextImgBig"
    ): array {
        return [
            "text" => $text,
            "image_url" => $image_url,
            "type" => $type
        ];
    }

    public static function buttonOpenChat(
        string $id,
        string $text,
        string $object_guid,
        string $object_type = "User"
    ): array {
        return [
            "id" => $id,
            "type" => "Link",
            "button_text" => $text,
            "button_link" => [
                "type" => "openchat",
                "open_chat_data" => [
                    "object_guid" => $object_guid,
                    "object_type" => $object_type
                ]
            ]
        ];
    }

    public static function buttonCalendar(
        string $id,
        string $title,
        string $type,
        ?string $default_value = null,
        ?string $min_year = null,
        ?string $max_year = null
    ): array {
        $calendar = [
            "title" => $title,
            "type" => $type
        ];

        if (!is_null($default_value)) {
            $calendar["default_value"] = $default_value;
        }

        if (!is_null($min_year)) {
            $calendar["min_year"] = $min_year;
        }

        if (!is_null($max_year)) {
            $calendar["max_year"] = $max_year;
        }

        return [
            "id" => $id,
            "type" => "Calendar",
            "button_text" => $title,
            "button_calendar" => $calendar
        ];
    }

    public static function buttonNumberPicker(
        string $id,
        string $title,
        string $min_value,
        string $max_value,
        ?string $default_value = null
    ): array {
        $picker = [
            "title" => $title,
            "min_value" => $min_value,
            "max_value" => $max_value
        ];

        if (!is_null($default_value)) {
            $picker["default_value"] = $default_value;
        }

        return [
            "id" => $id,
            "type" => "NumberPicker",
            "button_text" => $title,
            "button_number_picker" => $picker
        ];
    }

    public static function buttonStringPicker(
        ?string $id,
        ?string $title,
        array $items,
        ?string $default_value = null
    ): array {
        $picker = [
            "items" => $items
        ];

        if (!is_null($default_value)) {
            $picker["default_value"] = $default_value;
        }

        if (!is_null($title)) {
            $picker["title"] = $title;
        }

        return [
            "id" => $id,
            "type" => "StringPicker",
            "button_text" => $title ?? "انتخاب",
            "button_string_picker" => $picker
        ];
    }

    public static function buttonLocation(
        string $id,
        string $type,
        string $location_image_url,
        ?array $default_pointer_location = null,
        ?array $default_map_location = null,
        ?string $title = null
    ): array {
        $loc = [
            "type" => $type,
            "location_image_url" => $location_image_url
        ];

        if (!is_null($default_pointer_location)) {
            $loc["default_pointer_location"] = $default_pointer_location;
        }

        if (!is_null($default_map_location)) {
            $loc["default_map_location"] = $default_map_location;
        }

        if (!is_null($title)) {
            $loc["title"] = $title;
        }

        return [
            "id" => $id,
            "type" => "Location",
            "button_text" => $title ?? "موقعیت مکانی",
            "button_location" => $loc
        ];
    }

    public static function buttonTextbox(
        string $id,
        ?string $title,
        string $type_line,
        string $type_keypad,
        ?string $place_holder = null,
        ?string $default_value = null
    ): array {
        $textbox = [
            "type_line" => $type_line,
            "type_keypad" => $type_keypad
        ];

        if (!is_null($place_holder)) {
            $textbox["place_holder"] = $place_holder;
        }

        if (!is_null($default_value)) {
            $textbox["default_value"] = $default_value;
        }

        if (!is_null($title)) {
            $textbox["title"] = $title;
        }

        return [
            "id" => $id,
            "type" => "Textbox",
            "button_text" => $title ?? "متن",
            "button_textbox" => $textbox
        ];
    }
    
    public static function buttonPayment(
        string $id,
        string $title,
        int $amount,
        ?string $description = null
    ): array {
        $payment = [
            "title" => $title,
            "amount" => $amount
        ];

        if (!is_null($description)) {
            $payment["description"] = $description;
        }

        return [
            "id" => $id,
            "type" => "Payment",
            "button_text" => $title,
            "button_payment" => $payment
        ];
    }

    public static function buttonCameraImage(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "CameraImage",
            "button_text" => $title
        ];
    }

    public static function buttonCameraVideo(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "CameraVideo",
            "button_text" => $title
        ];
    }

    public static function buttonGalleryImage(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "GalleryImage",
            "button_text" => $title
        ];
    }

    public static function buttonGalleryVideo(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "GalleryVideo",
            "button_text" => $title
        ];
    }

    public static function buttonFile(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "File",
            "button_text" => $title
        ];
    }

    public static function buttonAudio(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "Audio",
            "button_text" => $title
        ];
    }

    public static function buttonRecordAudio(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "RecordAudio",
            "button_text" => $title
        ];
    }

    public static function buttonMyPhoneNumber(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "MyPhoneNumber",
            "button_text" => $title
        ];
    }

    public static function buttonMyLocation(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "MyLocation",
            "button_text" => $title
        ];
    }

    public static function buttonLink(
        string $id,
        string $title,
        string $url
    ): array {
        return [
            "id" => $id,
            "type" => "Link",
            "button_text" => $title,
            "url" => $url
        ];
    }

    public static function buttonAskMyPhoneNumber(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "AskMyPhoneNumber",
            "button_text" => $title
        ];
    }

    public static function buttonAskLocation(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "AskLocation",
            "button_text" => $title
        ];
    }

    public static function createLinkButton(
        string $title,
        string $url
    ): array {
        return [
            "type" => "Link",
            "button_text" => $title,
            "url" => $url
        ];
    }

    public static function buttonBarcode(
        string $id,
        string $title
    ): array {
        return [
            "id" => $id,
            "type" => "Barcode",
            "button_text" => $title
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
