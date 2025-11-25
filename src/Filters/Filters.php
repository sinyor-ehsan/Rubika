<?php
namespace Botkaplus;

class Filters {
    
    public static function text($expectedText = null) {
        return new class($expectedText) {
            private $expectedText;

            public function __construct($expectedText) {
                $this->expectedText = $expectedText;
            }

            public function match($message) {
                if (!isset($message->new_message?->text)) return false;

                return $this->expectedText === null || $this->expectedText === ''
                    || trim($message->new_message->text) === $this->expectedText;
            }
        };
    }

    public static function regex($pattern = null) {
        return new class($pattern) {
            private $pattern;

            public function __construct($pattern) {
                $this->pattern = $pattern;
            }

            public function match($message) {
                if (!isset($message->new_message?->text)) return false;

                $text = trim($message->new_message->text);

                // اگر الگو خالی یا null باشد، همیشه true برمی‌گرداند
                if ($this->pattern === null || $this->pattern === '') {
                    return true;
                }

                // بررسی تطابق با regex
                return preg_match('/' . $this->pattern . '/u', $text) === 1;
            }
        };
    }

    public static function command(array|string|null $expectedCommand = null, array $prefixes = ["/"]) {
        return new class($expectedCommand, $prefixes) {
            private array $expectedCommands;
            private array $prefixes;

            public function __construct(array|string|null $expectedCommand, array $prefixes) {
                // اگر رشته بود تبدیل به آرایه با یک عضو
                if ($expectedCommand === null || $expectedCommand === '') {
                    $this->expectedCommands = [];
                } else {
                    $this->expectedCommands = is_array($expectedCommand) ? $expectedCommand : [$expectedCommand];
                }

                $this->prefixes = $prefixes;
            }

            public function match($message): bool {
                if (!isset($message->new_message) || !isset($message->new_message->text)) {
                    return false;
                }

                $text = trim($message->new_message->text);

                // بررسی اینکه متن با یکی از prefixها شروع می‌شود
                $matchedPrefix = null;
                foreach ($this->prefixes as $prefix) {
                    if (strpos($text, $prefix) === 0) {
                        $matchedPrefix = $prefix;
                        break;
                    }
                }

                if ($matchedPrefix === null) {
                    return false; // هیچ prefix مطابق نبود
                }

                // اگر هیچ کامندی مشخص نشده بود، هر کامندی رو قبول کن
                if (empty($this->expectedCommands)) {
                    return true;
                }

                // حذف prefix از متن برای مقایسه
                $commandText = substr($text, mb_strlen($matchedPrefix));

                // بررسی اینکه کامند در لیست مجاز هست یا نه
                foreach ($this->expectedCommands as $cmd) {
                    if ($commandText === ltrim($cmd, $matchedPrefix)) {
                        return true;
                    }
                }
                return false;
            }
        };
    }

    public static function buttonId(array|string|null $expectedId = null) {
        return new class($expectedId) {
            private array $expectedIds;

            public function __construct(array|string|null $expectedId) {
                if ($expectedId === null || $expectedId === '') {
                    $this->expectedIds = [];
                } else {
                    $this->expectedIds = is_array($expectedId) ? $expectedId : [$expectedId];
                }
            }

            public function match($message): bool {
                $buttonId = $message->aux_data->button_id ?? null;

                // اگر هیچ آی‌دی مشخص نشده بود، همیشه true
                if (empty($this->expectedIds)) {
                    return true;
                }

                return in_array($buttonId, $this->expectedIds, true);
            }
        };
    }

    public static function chatId(array|string $allowed_ids): object {
        return new class($allowed_ids) {
            private array $allowed_ids;

            public function __construct(array|string $allowed_ids) {
                // اگر رشته بود، تبدیل به آرایه با یک عضو
                $this->allowed_ids = is_array($allowed_ids) ? $allowed_ids : [$allowed_ids];
            }

            public function match($message): bool {
                return isset($message->chat_id) && in_array($message->chat_id, $this->allowed_ids, true);
            }
        };
    }

    public static function senderId(string|array $allowed_ids): object {
        return new class($allowed_ids) {
            private array $allowed_ids;

            public function __construct(string|array $allowed_ids) {
                // اگر رشته بود، تبدیل به آرایه کن
                if (is_string($allowed_ids)) {
                    $this->allowed_ids = [$allowed_ids];
                } else {
                    $this->allowed_ids = $allowed_ids;
                }
            }

            public function match($message): bool {
                return isset($message->new_message) &&
                    isset($message->new_message->sender_id) &&
                    in_array($message->new_message->sender_id, $this->allowed_ids, true);
            }
        };
    }

    public static function private(): object {
        return new class {
            public function match($message): bool {
                return isset($message->chat_id) && str_starts_with($message->chat_id, "b0");
            }
        };
    }

    public static function group(): object {
        return new class {
            public function match($message): bool {
                return isset($message->chat_id) && str_starts_with($message->chat_id, "g0");
            }
        };
    }

    public static function channel(): object {
        return new class {
            public function match($message): bool {
                return isset($message->chat_id) && str_starts_with($message->chat_id, "c0");
            }
        };
    }

    public static function replied() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->reply_to_message_id);
            }
        };
    }

    public static function sticker() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->sticker);
            }
        };
    }

    public static function hasFile() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->file);
            }
        };
    }

    public static function file() {
        return new class {
            public function match($message) {
                $fileName = $message->new_message?->file->file_name ?? null;
                if (!$fileName) return false;

                $allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'rar', 'wav', 'apk'];
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                return in_array($extension, $allowedExtensions, true);
            }
        };
    }

    public static function photo() {
        return new class {
            public function match($message) {
                $fileName = $message->new_message?->file->file_name ?? null;
                if (!$fileName) return false;

                $imageExtensions = ['jpg', 'jpeg', 'png'];
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                return in_array($extension, $imageExtensions, true);
            }
        };
    }

    public static function video() {
        return new class {
            public function match($message) {
                $fileName = $message->new_message?->file->file_name ?? null;
                if (!$fileName) return false;

                $videoExtensions = ['mp4', 'mov', 'mkv', 'avi', 'webm'];
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                return in_array($extension, $videoExtensions, true);
            }
        };
    }

    public static function music() {
        return new class {
            public function match($message) {
                $file = $message->new_message?->file ?? null;
                if (!$file || !isset($file->file_name)) return false;

                $musicExtensions = ['mp3', 'wav', 'flac', 'aac', 'm4a'];
                $extension = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));

                return in_array($extension, $musicExtensions);
            }
        };
    }

    public static function voice() {
        return new class {
            public function match($message) {
                $fileName = $message->new_message?->file->file_name ?? null;
                if (!$fileName) return false;

                return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'ogg';
            }
        };
    }

    public static function gif() {
        return new class {
            public function match($message) {
                $fileName = $message->new_message?->file->file_name ?? null;
                if (!$fileName) return false;

                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                return $extension === 'gif';
            }
        };
    }

    public static function forward() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->forwarded_from);
            }
        };
    }

    public static function forwardFromChannel() {
        return new class {
            public function match($message) {
                $forward = $message->new_message?->forwarded_from ?? null;
                if (!$forward || !isset($forward->type_from, $forward->from_chat_id)) return false;

                return $forward->type_from === 'Channel';
            }
        };
    }

    public static function location() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->location);
            }
        };
    }

    public static function poll() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->poll);
            }
        };
    }

    /**
     * User or Bot or Channel
     * @param string $expectedType The expected sender type to match ('User', 'Bot', 'Channel')
     */
    public static function senderType($expectedType) {
        return new class($expectedType) {
            private $expectedType;

            public function __construct($expectedType) {
                $this->expectedType = $expectedType;
            }

            public function match($message) {
                return isset($message->new_message?->sender_type)
                    && $message->new_message->sender_type === $this->expectedType;
            }
        };
    }

    public static function metadata() {
        return new class {
            public function match($message) {
                return isset($message->new_message?->metadata?->meta_data_parts)
                    && is_array($message->new_message->metadata->meta_data_parts)
                    && count($message->new_message->metadata->meta_data_parts) > 0;
            }
        };
    }

    // فیلتر ترکیبی and. همه فیلتر ها برقرار باشن
    public static function and(...$filters) {
        return new class($filters) {
            private $filters;

            public function __construct($filters) {
                $this->filters = $filters;
            }

            public function match($message) {
                foreach ($this->filters as $filter) {
                    if (!$filter->match($message)) {
                        return false;
                    }
                }
                return true;
            }
        };
    }

    // فیلتر ترکیبی or. یک یا چند فیلتر برقرار باشد.
    public static function or(...$filters) {
        return new class($filters) {
            private $filters;

            public function __construct($filters) {
                $this->filters = $filters;
            }

            public function match($message) {
                foreach ($this->filters as $filter) {
                    if ($filter->match($message)) {
                        return true;
                    }
                }
                return false;
            }
        };
    }

    //فیلتر ترکیبی not. اگر فیلتر های دیگر اجرا نشود این اجرا میکند.
    // $bot->onMessage(Filters::not(Filters::command()), function($bot, Message $message) {
    //     $message->replyMessage("این پیام کامند نبود ✅");
    //     });
    public static function not($filter) {
        return new class($filter) {
            private $filter;

            public function __construct($filter) {
                $this->filter = $filter;
            }

            public function match($message) {
                return !$this->filter->match($message);
            }
        };
    }


}
