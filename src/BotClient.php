<?php

namespace Botkaplus;

require_once 'Message/Message.php';
require_once 'Filters/Filters.php';
require_once 'Keypad/KeypadChat.php';
require_once 'Keypad/KeypadInline.php';
require_once 'Metadata/Metadata.php';
require_once 'Metadata/Utils.php';
require_once 'Metadata/Metadata_Mode.php';

use Botkaplus\Message;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class BotClient {

    private $token;
    private $rData;
    private $url_webhook;
    private $propagationStopped = false;
    private $timeout;
    private $max_retries;
    private $parse_mode;

    // پیام خام دریافتی از روبیکا
    public $message;
    public $new_message; // پیام خام برای فیلترها
    public $message_wrapper; // کلاس ریپلای حرفه‌ای

    // فیلدهای inline
    public $inline_message;
    
    // فیلدهای پیام ویرایش شده
    public $updated_message;

    // هندلرها
    private $handlers = [];

    // سازنده کلاس
    public function __construct($token, $rData = null, $timeout = 30, $max_retries = 3, $parse_mode = "MarkdownMode", $url_webhook = null) {
        $this->token = $token;
        $this->rData = $rData;
        $this->timeout = $timeout;
        $this->max_retries = $max_retries;
        $this->parse_mode = $parse_mode;
        $this->url_webhook = $url_webhook;
        if ($url_webhook !== null) {$this->setWebhook($url_webhook);}
        if ($rData !== null) {$this->get_rData($rData);}
    }

    // استخراج داده‌ها از ورودی
    private function get_rData($rData) {
        $this->inline_message       = $rData->inline_message ?? null;
        $this->message              = $rData->update ?? $this->inline_message;
        $this->new_message          = $this->message->new_message ?? null;
        $this->updated_message      = $this->message->updated_message ?? null;
        // ساخت کلاس ریپلای
        $this->message_wrapper = new Message($this, $rData);
    }

    public function setWebhook($url_webhook) {
        echo "fix endpoint Rubika\n";
        $endpoints = [
            "ReceiveUpdate",
            "ReceiveInlineMessage",
            "ReceiveQuery",
            "GetSelectionItem",
            "SearchSelectionItems"
        ];

        foreach ($endpoints as $endpoint) {
            $data = [
                "url" => $url_webhook,
                "type" => $endpoint
            ];

            try {
                $raw = $this->bot("updateBotEndpoints", $data);
                $response = json_decode($raw);

                echo $endpoint . ":\n";

                if (isset($response->status) && $response->status === "OK") {
                    $statusText = isset($response->data->status) ? $response->data->status : "unknown";
                    echo "   ✅ done - status: " . $statusText . "\n";
                } else {
                    echo "   ❌ error - response: " . json_encode($response) . "\n";
                }

            } catch (\Exception $e) {
                echo $endpoint . ":\n";
                echo "   ❌ error Network: " . $e->getMessage() . PHP_EOL . "\n";
            }

            usleep(500000);
        }

        echo "the end!";
    }

    // ثبت هندلر
    public function onMessage($filter, $callback) {
        $this->handlers[] = [
            'filter' => $filter,
            'callback' => $callback,
            'type' => 'message'
        ];
    }

    public function onInlineMessage($filter, $callback) {
        $this->handlers[] = [
            'filter' => $filter,
            'callback' => $callback,
            'type' => 'inline'
        ];
    }

    public function onUpdatedMessage($filter, $callback) {
        $this->handlers[] = [
            'filter' => $filter,
            'callback' => $callback,
            'type' => 'updated'
        ];
    }

    public function run() { // ✅ اجرای هندلرها در حالت webhook
        foreach ($this->handlers as $handler) {
            $filter = $handler['filter'];
            $type   = $handler['type'] ?? 'message';

            if ($type === 'message' && $this->new_message) {
                if ($filter === null || $filter->match($this->message)) {
                    call_user_func($handler['callback'], $this, $this->message_wrapper);
                    if ($this->propagationStopped) break;
                }
            } else if ($type === 'inline' && $this->inline_message) {
                if ($filter === null || $filter->match($this->inline_message)) {
                    call_user_func($handler['callback'], $this, $this->message_wrapper);
                    if ($this->propagationStopped) break;
                }
            } else if ($type === 'updated' && $this->updated_message) {
                if ($filter === null || $filter->match($this->inline_message)) {
                    call_user_func($handler['callback'], $this, $this->message_wrapper);
                    if ($this->propagationStopped) break;
                }
            }
        }
    }

    public function runPolling() {
        $offset_id = null;

        $last_message_ids = [];
        while (true) {
            try {

                $response = $this->getUpdates(limit: 100, offset_id: $offset_id);

                if (empty($response->data->updates)) {
                    sleep(2);
                    continue;
                }

                foreach ($response->data->updates as $update) {
                    $time = null;
                    $new_message_id = null;
                    if (isset($update->new_message->time)) {
                        $time = $update->new_message->time;
                        $new_message_id = $update->new_message->message_id;
                    } else if (isset($update->updated_message->time)) {
                        $time = $update->updated_message->time;
                        $new_message_id = $update->new_message->message_id;
                    }

                    if (count($last_message_ids) >= 20) {
                        array_shift($last_message_ids);
                    }
                    
                    if ($this->has_time_passed($time, 5)) {
                        continue;
                    }

                    if (!in_array($new_message_id, $last_message_ids)) {
                        $this->rData = (object)['update' => $update];
                        $this->get_rData($this->rData);
                        $this->run();
                        $last_message_ids[] = $new_message_id;
                    }
                    usleep(500000);
                }

                if (isset($response->data->next_offset_id)) {
                    $offset_id = $response->data->next_offset_id;
                }

            } catch (\Exception $e) {
                echo "خطا در polling: " . $e->getMessage() . PHP_EOL;
                sleep(5); // مکث قبل از تلاش مجدد
            }
        }
    }

    public function getUpdates($limit = 100, $offset_id = null) {
        $data_send = ['limit' => $limit];
        if ($offset_id) {$data_send['offset_id'] = $offset_id;}
        return json_decode($this->bot("getUpdates", $data_send));
    }

    public function getMe() {
        $response = $this->bot("getMe");
        return $response;
    }

    function parseTextMetadata($text, $parseMode) {
        $formatter = new TrackParsed();
        $parsed = $formatter->parse($text, $parseMode);

        $resultText = isset($parsed['text']) ? $parsed['text'] : null;
        $resultMetadata = isset($parsed['metadata']) ? $parsed['metadata'] : null;

        return [$resultText, $resultMetadata];
    }


    /**
     * ارسال پیام به چت
     *
     * این متد یک پیام متنی به چت مشخص‌شده ارسال می‌کند.
     *
     * @param string $chat_id شناسه چت مقصد
     * @param string $text متن پیام
     * @param array $inline_keypad برای ارسال کیبورد
     * @param string|null $reply_to_message_id شناسه پیام برای پاسخ (اختیاری)
     * @return stdClass شیء پاسخ از سرور. موفقیت یا شکست ارسال پیام
     */
    public function sendMessage($chat_id, $text, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = "New", $reply_to_message = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        $data_send = [
            "chat_id" => $chat_id,
            "text" => $text,
            "reply_to_message_id" => $reply_to_message
        ];
        if (!empty($text) & $metadata == null) {
            list($text, $metadata) = $this->parseTextMetadata($text, $parse_mode);
            $data_send["text"] = $text;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        
        return json_decode($this->bot("sendMessage", $data_send));
    }

    /**
     * ارسال نظرسنجی به چت
     * این متد یک نظرسنجی به چت مشخص‌شده ارسال می‌کند.
     * @param string $chat_id شناسه چت مقصد
     * @param string $question متن سوال
     * @param array[string] گزینه های سوال
     * @param string ["Regular", "Quiz"] = "Regular" نوع
     * @param string allows_multiple_answers .کاربرد دارد "regular" فقط برای نوع
     * @param string is_anonymous باشد، رأی‌دهی ناشناس است و نام رأی‌دهندگان نمایش داده نمی‌شود true اگر 
     * @param string correct_option_index "Quiz" گزینه درست در حالت 
     * @param string hint توضیح نظرسنجی
     */
    public function sendPoll($chat_id, string $question, array $options, $type = "Regular", $allows_multiple_answers = null, $is_anonymous = true, $correct_option_index = null, $hint = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = "New", $reply_to_message = null){
        $data_send = [
            "chat_id" => $chat_id,
            "question" => $question,
            "options" => $options,
            "type" => $type,
            "allows_multiple_answers" => $allows_multiple_answers,
            "is_anonymous" => $is_anonymous,
            "correct_option_index" => $correct_option_index,
            "explanation" => $hint,
        ];
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        return $this->bot("sendPoll", $data_send);
    }

    public function sendLocation($chat_id, $latitude, $longitude, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = "New", $reply_to_message = null) {
        $data_send = [
            "chat_id" => $chat_id,
            "latitude" => $latitude,
            "longitude" => $longitude
        ];
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        return $this->bot("sendLocation", $data_send);
    }

    function has_time_passed($last_time, $seconds = 5) {
        try {
            $timestamp = (int) floatval($last_time);
            $now = time();
            return ($now - $timestamp) > $seconds;
        } catch (Exception $e) {
            return false;
        }
    }

    public function sendContact($chat_id, $first_name, $last_name, $phone_number, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = "New", $reply_to_message = null){
        $data_send = [
            "chat_id" => $chat_id,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "phone_number" => $phone_number
        ];
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        return $this->bot("sendContact", $data_send);
    }

    public function sendSticker($chat_id, $sticker_id, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = "New", $reply_to_message = null) {
        $data_send = [
            "chat_id" => $chat_id,
            "sticker_id" => $sticker_id
        ];
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        return $this->bot("sendSticker", $data_send);
    }

    /**
     * گرفتن اطلاعات چت
     *
     * این متد اطلاعات چت را دریافت می‌کند.
     *
     * @param string $chat_id شناسه چت مقصد
     */
    public function getChat($chat_id) {
        return $this->bot(method:"getChat", data:["chat_id" => $chat_id]);
    }

    public function forward_Message($from_chat_id, $messagee_id, $to_chat_id) {
        $data_send = [
            "from_chat_id" => $from_chat_id,
            "message_id" => $messagee_id,
            "to_chat_id" => $to_chat_id,
        ];
        return $this->bot("forwardMessage", $data_send);
    }

    /**
     * ویرایش پیام
     *
     * این متد پیام را ویرایش می‌کند.
     *
     * @param string $chat_id شناسه چت مقصد
     * @param string $text متن پیام ویرایش شده
     * @param string $id_message شناسه پیام مورد نظر
     * @param string $data_message اختیاری پیام ارسال شده توسط ربات send_Message.
     */
    public function editMessageText($chat_id, $text, $id_message = null, $data_messade = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        $data_send = [
            "chat_id" => $chat_id,
            "text" => $text
        ];
        if ($id_message !== null){$data_send["message_id"] = $id_message;}
        else if ($data_messade !== null) {$data_send["message_id"] = $data_messade->data->message_id;}
        if (!empty($text) & $metadata == null) {
            list($text, $metadata) = $this->parseTextMetadata($text, $parse_mode);
            $data_send["text"] = $text;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}
        return $this->bot("editMessageText", $data_send);
    }

    public function editMessageInlineKeypad($chat_id, $id_message, $inline_keypad) {
        $data_send = [
            "chat_id" => $chat_id,
            "message_id" => $id_message,
            "inline_keypad" => $inline_keypad
        ];
        return $this->bot("editMessageKeypad", $data_send);
    }

    public function deleteMessage($chat_id, $id_message) {
        return $this->bot("deleteMessage", ["chat_id" => $chat_id, "message_id" => $id_message]);
    }

    /**
     * تنظیم کامندها
     *
     * این متد کامندهای بات را تنظیم می‌کند.
     *
     * @param array $bot_commands = [["command" => "text_command1", "description" => "text_description1"], [], ...] $bot_commands لیست کامندها و دیسکریپشن ها
     */
    public function setCommands($bot_commands) {
        return $this->bot("setCommands", ["bot_commands" => $bot_commands]);
    }

    public function deleteChatKeypad($chat_id) {
        return $this->bot("editChatKeypad", ["chat_id" => $chat_id, "chat_keypad_type" => "Remove"]);
    }

    public function editChatKeypad($chat_id, $chat_keypad, $chat_keypad_type = "New") {
        return $this->bot("editChatKeypad", ["chat_id" => $chat_id, "chat_keypad" => $chat_keypad, "chat_keypad_type" => $chat_keypad_type]);
    }

    public function getFile($file_id) {
        return $this->bot("getFile", ["file_id" => $file_id]);
    }

    public function downloadFile(
        string $file_id,
        ?string $save_as = null,
        ?callable $progress = null,
        ?int $chunk_size = 65536,
        ?bool $as_bytes = false,
        ?string $file_name = null,
        ?float $timeout = 20.0
    ) {
        // گرفتن لینک دانلود از API
        $response = json_decode($this->bot("getFile", ["file_id" => $file_id]));
        if (!$response || empty($response->data->download_url)) {
            throw new InvalidArgumentException("Invalid file_id: {$file_id}");
        }

        $download_url = $response->data->download_url;

        // تنظیم context برای timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
            ]
        ]);

        $fp = fopen($download_url, 'rb', false, $context);
        if (!$fp) {
            throw new RuntimeException("Failed to open download URL: {$download_url}");
        }

        // گرفتن سایز کل فایل (اگر موجود بود)
        $headers = get_headers($download_url, true);
        $total_size = isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;

        $downloaded = 0;

        if ($as_bytes) {
            $content = '';
            while (!feof($fp)) {
                $chunk = fread($fp, $chunk_size);
                $content .= $chunk;
                $downloaded += strlen($chunk);

                if ($progress) {
                    try {
                        $progress($downloaded, $total_size);
                    } catch (\Throwable $e) {
                        // نادیده گرفتن خطا در callback
                    }
                }
            }
            fclose($fp);
            return $content;
        } else {
            if ($save_as === null) {
                $save_as = $file_name ?? ("downloaded_" . uniqid() . ".bin");
            }

            $out = fopen($save_as, 'wb');
            if (!$out) {
                fclose($fp);
                throw new RuntimeException("Failed to open file for writing: {$save_as}");
            }

            while (!feof($fp)) {
                $chunk = fread($fp, $chunk_size);
                fwrite($out, $chunk);
                $downloaded += strlen($chunk);

                if ($progress) {
                    try {
                        $progress($downloaded, $total_size);
                    } catch (\Throwable $e) {
                        // نادیده گرفتن خطا در callback
                    }
                }
            }

            fclose($fp);
            fclose($out);

            return $save_as;
        }
    }

    public function sendFileById($chat_id, $file_id, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = "New", $reply_to_message = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
        ];
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}
        return $this->bot("sendFile", $data_send);
    }

    /**
     * ارسال فایل
     *
     * این متد فایل ارسال می‌کند.
     *
     * @param string $file_id شناسه فایل مورد نظر
     * @param string $file_type in ['File', 'Image', 'Voice', 'Music', 'Gif', 'Video'] نوع فایل. 
     */
    public function sendFile(string $chat_id, ?string $file_path = null, ?string $file_id = null, ?string $file_type = null, ?string $caption = null, ?array $inline_keypad = null, ?array $chat_keypad = null, string $chat_keypad_type = 'New', ?string $reply_to_message = null, ?string $parse_mode = null, ?array $metadata = null): array {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if (!isset($file_id)) {
            if ($file_type === null){
                $mime_type = mime_content_type($file_path);
                $file_type = $this->detectFileType($mime_type);
            }
            
            $upload_url = $this->requestSendFile($file_type);
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }
        
        $data_send = [
            'chat_id' => $chat_id,
            'file_id' => $file_id,
        ];
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}
        $response = $this->bot('sendFile', $data_send);
        return ['data' => $response, 'file_id' => $file_id];
    }

    public function sendImage(string $chat_id, ?string $file_path = null, ?string $file_id = null, ?string $caption = null, ?array $inline_keypad = null, ?array $chat_keypad = null, string $chat_keypad_type = 'New', ?string $reply_to_message = null, ?string $parse_mode = null, ?array $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Image");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
        ];

        if ($reply_to_message !== null) {$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null) {
            $data_send["inline_keypad"] = $inline_keypad;
        } else if ($chat_keypad !== null) {
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}

        return $this->bot("sendFile", $data_send);
    }
    
    public function sendVoice(string $chat_id, ?string $file_path = null, ?string $file_id = null, ?string $caption = null, ?array $inline_keypad = null, ?array $chat_keypad = null, string $chat_keypad_type = 'New', ?string $reply_to_message = null, ?string $parse_mode = null, ?array $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Voice");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
        ];

        if ($reply_to_message !== null) {$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null) {
            $data_send["inline_keypad"] = $inline_keypad;
        } elseif ($chat_keypad !== null) {
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}

        return $this->bot("sendFile", $data_send);
    }

    public function sendMusic(string $chat_id, ?string $file_path = null, ?string $file_id = null, ?string $caption = null, ?array $inline_keypad = null, ?array $chat_keypad = null, string $chat_keypad_type = 'New', ?string $reply_to_message = null, ?string $parse_mode = null, ?array $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Music");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
        ];

        if ($reply_to_message !== null) {$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null) {
            $data_send["inline_keypad"] = $inline_keypad;
        } elseif ($chat_keypad !== null) {
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}

        return $this->bot("sendFile", $data_send);
    }

    public function sendGif(string $chat_id, ?string $file_path = null, ?string $file_id = null, ?string $caption = null, ?array $inline_keypad = null, ?array $chat_keypad = null, string $chat_keypad_type = 'New', ?string $reply_to_message = null, ?string $parse_mode = null, ?array $metadata = null): array {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if (!isset($file_id)) {
            $mime_type = mime_content_type($file_path);
            $file_type = $this->detectFileType($mime_type);
            if ($file_type === "Gif" || $file_type === "Video") {$file_type = "Gif";}
            $upload_url = $this->requestSendFile($file_type);
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }
        
        $data_send = [
            'chat_id' => $chat_id,
            'file_id' => $file_id,
        ];
        if ($reply_to_message !== null){$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null){$data_send["inline_keypad"] = $inline_keypad;}
        else if($chat_keypad !== null){
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}
        $response = $this->bot('sendFile', $data_send);
        return ['data' => $response, 'file_id' => $file_id];
    }

    public function sendVideo(string $chat_id, ?string $file_path = null, ?string $file_id = null, ?string $caption = null, ?array $inline_keypad = null, ?array $chat_keypad = null, string $chat_keypad_type = 'New', ?string $reply_to_message = null, ?string $parse_mode = null, ?array $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Video");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
        ];

        if ($reply_to_message !== null) {$data_send["reply_to_message_id"] = $reply_to_message;}
        if ($inline_keypad !== null) {
            $data_send["inline_keypad"] = $inline_keypad;
        } elseif ($chat_keypad !== null) {
            $data_send["chat_keypad"] = $chat_keypad;
            $data_send["chat_keypad_type"] = $chat_keypad_type;
        }
        if (!empty($caption) & $metadata == null) {
            list($caption, $metadata) = $this->parseTextMetadata($caption, $parse_mode);
            $data_send["text"] = $caption;
            if (!empty($metadata)) {
                $data_send["metadata"] = $metadata;
            }
        } else {$data_send["metadata"] = $metadata;}

        return $this->bot("sendFile", $data_send);
    }

    // مرحله اول: دریافت آدرس آپلود فایل
    function requestSendFile($type) {
        $validTypes = ['File', 'Image', 'Voice', 'Music', 'Gif', 'Video'];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid file type: {$type}");
        }

        $data = ["type" => $type];
        $response = json_decode($this->bot("requestSendFile", $data));
        return $response->data->upload_url;
    }

    // مرحله دوم: آپلود فایل به آدرس دریافتی
    function uploadFileToRubika($upload_url, $file_path) {
        $cfile = curl_file_create($file_path);
        $data = ['file' => $cfile];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $upload_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result);
        return $response->data->file_id;
    }

    private function detectFileType(string $mime_type): string {
        $map = [
            'image/jpeg' => 'Image',
            'image/png' => 'Image',
            'image/gif' => 'Gif',
            'video/mp4' => 'Video',
            'video/quicktime' => 'Video',
            'audio/mpeg' => 'Music',
            'audio/wav' => 'File',
            'application/pdf' => 'File',
            'application/msword' => 'File',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'File',
            'application/zip' => 'File',
            'application/x-rar-compressed' => 'File',
        ];
        return $map[strtolower($mime_type)] ?? 'File';
    }

    public function stopPropagation() {
        $this->propagationStopped = true;
    }

    public function toString($data_json) {
        return json_encode($data_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function bot(string $method, array $data = []): string
    {
        $urls = [
            "https://botapi.rubika.ir/v3/",
            "https://messengerg2b1.iranlms.ir/v3/"
        ];

        foreach ($urls as $base) {
            $url = $base . $this->token . "/" . $method;
            $retry = 0;

            while ($retry < $this->max_retries) {
                $ch = curl_init($url);

                try {
                    if (!empty($data)) {
                        $array_setopt = [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_POSTFIELDS => json_encode($data),
                            CURLOPT_TIMEOUT => $this->timeout
                        ];
                    }else{
                        $array_setopt = [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_TIMEOUT => $this->timeout
                        ];
                    }
                    curl_setopt_array($ch, $array_setopt);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    if ($response === false) {
                        throw new \Exception("cURL error: " . curl_error($ch));
                    }

                    if ($httpCode >= 200 && $httpCode < 300) {
                        return $response; // موفقیت
                    }

                    throw new \Exception("API Error: HTTP {$httpCode} - " . ($response ?: 'No response'));
                } catch (\Exception $e) {
                    $retry++;
                    if ($retry === $this->max_retries) {
                        // اگر همه تلاش‌ها ناموفق بود، می‌رویم سراغ آدرس بعدی
                        break;
                    }
                    usleep(500000); // 0.5 ثانیه مکث بین تلاش‌ها
                } finally {
                    curl_close($ch);
                }
            }
        }

        return json_encode(['ok' => false, 'error' => 'Request failed on all endpoints']);
    }

}

?>

