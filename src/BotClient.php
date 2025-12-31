<?php

namespace Botkaplus;

require_once 'Message/Message.php';
require_once 'Filters/Filters.php';
require_once 'Keypad/ChatKeypad.php';
require_once 'Keypad/InlineKeypad.php';
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
    private $webhook = false;
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
    public function __construct($token, $rData = null, $timeout = 30, $max_retries = 3, $parse_mode = "MarkdownMode") {
        $this->token = $token;
        $this->rData = $rData;
        $this->timeout = $timeout;
        $this->max_retries = $max_retries;
        $this->parse_mode = $parse_mode;
        if ($rData !== null) {$this->get_rData($rData);}
    }

    private function get_rData($rData) {
        $this->inline_message       = $rData->inline_message ?? null;
        $this->message              = $rData->update ?? $this->inline_message;
        $this->new_message          = $this->message->new_message ?? null;
        $this->updated_message      = $this->message->updated_message ?? null;
        $this->message_wrapper = new Message($this, $rData);
    }

    public function setWebhook($url_webhook) {
        echo "setting up Rubika endpoints ...\n";
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

        echo "the end!" . PHP_EOL;
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

    public function runHandlers() {
        foreach ($this->handlers as $handler) {

            $filter = $handler['filter'];
            $type   = $handler['type'] ?? 'message';

            $target = match ($type) {
                'message' => $this->new_message ? $this->message : null,
                'inline'  => $this->inline_message,
                'updated' => $this->updated_message,
                default   => null,
            };

            if (!$target) {
                continue;
            }

            // $filter->match($target)
            if ($filter === null || $filter->match($this->message_wrapper)) {

                try {
                    call_user_func($handler['callback'], $this, $this->message_wrapper);
                } catch (\Throwable $e) {
                    // ANSI Colors
                    $red     = "\033[31m";
                    $yellow  = "\033[33m";
                    $cyan    = "\033[36m";
                    $reset   = "\033[0m";
                    $bold    = "\033[1m";

                    echo "{$yellow}⚠️  {$bold}Error in callback{$reset}\n";

                    echo "{$cyan}Message:{$reset} {$red}" . $e->getMessage() . "{$reset}\n";
                    echo "{$cyan}File:{$reset} " . $e->getFile() . "\n";
                    echo "{$cyan}Eror in Line:{$reset} " . $e->getLine() . "\n";

                    echo "{$cyan}Stack trace:{$reset}\n";
                    echo $e->getTraceAsString() . "\n";

                    echo "{$yellow}" . str_repeat("-", 50) . "{$reset}\n";
                }

                if ($this->propagationStopped) {
                    break;
                }
            }
        }
    }

    public function run(): void
    {

        // دریافت ورودی
        $body = file_get_contents("php://input");

        if (!$body) {
            echo "OK";
            return;
        }

        $Data = json_decode($body);

        if (!$Data) {
            echo "OK";
            return;
        }

        $this->rData = $Data;

        $this->get_rData($Data);
        $this->runHandlers();

        echo "OK";
    }

    public function runTest($url_webhook=null, $path_webhook = "/webhook", $host = "0.0.0.0", $port = 8000, $set_webhook=true) {
        if ($set_webhook && $url_webhook !== null && $this->webhook === false) {
            $this->setWebhook($url_webhook . $path_webhook);
            $this->webhook = true;
        }

        $server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);

        if (!$server) {
            die("Cannot create server: $errstr");
        }

        echo "Server running at http://{$host}:{$port}{$path_webhook}\n";

        while (true) {
            try {
                $conn = @stream_socket_accept($server, -1);
                if (!$conn) continue;

                $request = fread($conn, 4096);

                preg_match('#(GET|POST) (.*?) HTTP#', $request, $match);
                $url = $match[2] ?? "/";
                $method = $match[1] ?? null;

                $green   = "\033[32m";
                $reset   = "\033[0m";
                echo "{$green}$method{$reset} $url\n";

                if ($url === $path_webhook) {

                    list($headers, $body) = explode("\r\n\r\n", $request, 2);

                    $Data = json_decode($body);

                    if ($Data) {

                        $this->rData = $Data;

                        $this->get_rData($Data);

                        $this->runHandlers();
                    }
                }

                $response = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\nOK";
                fwrite($conn, $response);
                fclose($conn);

            } catch (\Exception $e) {
                echo "خطا در webhook: " . $e->getMessage() . PHP_EOL;
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
                    usleep(500000);
                    continue;
                }

                foreach ($response->data->updates as $update) {
                    $time = null;
                    $new_message_id = null;
                    
                    if ($update->type === "RemovedMessage"){
                        continue;
                    }

                    if (isset($update->new_message->time)) {
                        $time = $update->new_message->time;
                        $new_message_id = $update->new_message->message_id;
                    } else if (isset($update->updated_message->time)) {
                        $time = time();
                        $new_message_id = $update->updated_message->message_id;
                    }

                    if (count($last_message_ids) >= 20) {
                        array_shift($last_message_ids);
                    }

                    if ($this->has_time_passed($time, 10)) {
                        continue;
                    }

                    if (!in_array($new_message_id, $last_message_ids)) {
                        $last_message_ids[] = $new_message_id;
                        $this->rData = (object)['update' => $update];
                        $this->get_rData($this->rData);
                        $this->runHandlers();
                    }
                }

                if (isset($response->data->next_offset_id)) {
                    $offset_id = $response->data->next_offset_id;
                }
                usleep(100000);

            } catch (\Exception $e) {
                echo "خطا در polling: " . $e->getMessage() . PHP_EOL;
                sleep(2);
            }
        }
    }

    public function runAdaptivePolling()
    {
        $offset_id = null;
        $last_message_ids = [];

        $sleepTime = 500000;          // 0.5 ثانیه
        $maxSleepTime = 27000000;     // 27 ثانیه

        while (true) {
            try {
                $response = $this->getUpdates(limit: 100, offset_id: $offset_id);

                echo $sleepTime . PHP_EOL;

                // اگر آپدیتی نبود
                if (empty($response->data->updates)) {

                    usleep($sleepTime);

                    // افزایش تدریجی خواب (exponential backoff)
                    $sleepTime = min($sleepTime * 2, $maxSleepTime);
                    continue;
                }

                // اگر آپدیت داشت → ریست تایمر
                $sleepTime = 500000;

                foreach ($response->data->updates as $update) {

                    if ($update->type === "RemovedMessage") {
                        continue;
                    }

                    $time = null;
                    $new_message_id = null;

                    if (isset($update->new_message->time)) {
                        $time = $update->new_message->time;
                        $new_message_id = $update->new_message->message_id;
                    } elseif (isset($update->updated_message->time)) {
                        $time = time();
                        $new_message_id = $update->updated_message->message_id;
                    }

                    if (!$new_message_id) {
                        continue;
                    }

                    if (count($last_message_ids) >= 20) {
                        array_shift($last_message_ids);
                    }

                    if ($this->has_time_passed($time, 10)) {
                        continue;
                    }

                    if (!in_array($new_message_id, $last_message_ids, true)) {
                        $last_message_ids[] = $new_message_id;
                        $this->rData = (object)['update' => $update];
                        $this->get_rData($this->rData);
                        $this->runHandlers();
                    }
                }

                if (isset($response->data->next_offset_id)) {
                    $offset_id = $response->data->next_offset_id;
                }

                // تاخیر خیلی کوتاه بعد از پردازش موفق
                usleep(100000);

            } catch (\Exception $e) {
                echo "خطا در polling: " . $e->getMessage() . PHP_EOL;

                // در خطا هم کمی صبر کن
                sleep(2);
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

    public function getChatMember($chat_id, $user_id) {
        $data = ["chat_id" => $chat_id, "user_id" => $user_id];
        return $this->bot("getChatMember", $data);
    }

    public function pinChatMessage($chat_id, $message_id) {
        $data = ["chat_id" => $chat_id, "message_id" => $message_id];
        return $this->bot("pinChatMessage", $data);
    }

    public function unpinChatMessage($chat_id, $message_id) {
        $data = ["chat_id" => $chat_id, "message_id" => $message_id];
        return $this->bot("unpinChatMessage", $data);
    }

    public function unpinAllChatMessages($chat_id, $message_id=null) {
        $data = ["chat_id" => $chat_id];
        if ($message_id !== null) {$data["message_id"] = $message_id;}
        return $this->bot("unpinAllChatMessages", $data);
    }

    public function getChatAdministrators($chat_id) {
        return $this->bot("getChatAdministrators", ["chat_id" => $chat_id]);
    }

    public function getChatMemberCount($chat_id) {
        return $this->bot("getChatMemberCount", ["chat_id" => $chat_id]);
    }

    public function banChatMember($chat_id, $user_id) {
        $data = ["chat_id" => $chat_id, "user_id" => $user_id];
        return $this->bot("banChatMember", $data);
    }

    public function unbanChatMember($chat_id, $user_id) {
        $data = ["chat_id" => $chat_id, "user_id" => $user_id];
        return $this->bot("unbanChatMember", $data);
    }

    public function getMessagesById(string|array $message_ids) {
        if (!is_array($message_ids)) {$message_ids = [$message_ids];}

        $response = $this->getUpdates(limit: 100);

        if (empty($response->data->updates)) {return [];}

        $foundMessages = [];

        foreach ($response->data->updates as $update) {

            if (isset($update->new_message)) {
                $msg = $update->new_message;

                if (in_array($msg->message_id, $message_ids)) {
                    $foundMessages[$msg->message_id] = $update;
                }
            }

            if (isset($update->updated_message)) {
                $msg = $update->updated_message;

                if (in_array($msg->message_id, $message_ids)) {
                    $foundMessages[$msg->message_id] = $update;
                }
            }
        }

        return $foundMessages;
    }

    public function forwardMessage($from_chat_id, $messagee_id, $to_chat_id) {
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
            "text" => $caption
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

    /**
     * ارسال فایل
     *
     * این متد فایل ارسال می‌کند.
     *
     * @param string $file_id شناسه فایل مورد نظر
     * @param string $file_type in ['File', 'Image', 'Voice', 'Music', 'Gif', 'Video'] نوع فایل. 
     */
    public function sendFile($chat_id, $file_path = null, $file_id = null, $file_type = null, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = 'New', $reply_to_message = null, $parse_mode = null, $metadata = null): array {
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
            "text" => $caption
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

    public function sendImage($chat_id, $file_path = null, $file_id = null, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = 'New', $reply_to_message = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Image");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
            "text" => $caption
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

        $response = $this->bot('sendFile', $data_send);
        return ['data' => $response, 'file_id' => $file_id];
    }
    
    public function sendVoice($chat_id, $file_path = null, $file_id = null, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = 'New', $reply_to_message = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Voice");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
            "text" => $caption
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

        $response = $this->bot('sendFile', $data_send);
        return ['data' => $response, 'file_id' => $file_id];
    }

    public function sendMusic($chat_id, $file_path = null, $file_id = null, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = 'New', $reply_to_message = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Music");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
            "text" => $caption
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

        $response = $this->bot('sendFile', $data_send);
        return ['data' => $response, 'file_id' => $file_id];
    }

    public function sendGif($chat_id, $file_path = null, $file_id = null, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = 'New', $reply_to_message = null, $parse_mode = null, $metadata = null): array {
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
            "text" => $caption
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

    public function sendVideo($chat_id, $file_path = null, $file_id = null, $caption = null, $inline_keypad = null, $chat_keypad = null, $chat_keypad_type = 'New', $reply_to_message = null, $parse_mode = null, $metadata = null) {
        if ($parse_mode === null){$parse_mode = $this->parse_mode;}
        if ($file_path) {
            $upload_url = $this->requestSendFile("Video");
            $file_id = $this->uploadFileToRubika($upload_url, $file_path);
        }

        $data_send = [
            "chat_id" => $chat_id,
            "file_id" => $file_id,
            "text" => $caption
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

        $response = $this->bot('sendFile', $data_send);
        return ['data' => $response, 'file_id' => $file_id];
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

                $array_setopt = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => $this->timeout,
                ];
                if (!empty($data)) {
                    $array_setopt[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                curl_setopt_array($ch, $array_setopt);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                curl_close($ch);

                if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                    return $response; // موفقیت
                }

                // خطا → تلاش مجدد
                $retry++;
                if ($retry < $this->max_retries) {
                    usleep(500000); // 0.5 ثانیه مکث بین تلاش‌ها
                } else {
                    echo "API Error on {$url}: " . ($response ?: $error ?: 'No response') . PHP_EOL;
                }
            }
            // اگر همه‌ی تلاش‌ها روی این URL شکست خورد → میره سراغ URL بعدی
        }

        return json_encode(['ok' => false, 'error' => 'Request failed on all endpoints']);
    }

}

?>
