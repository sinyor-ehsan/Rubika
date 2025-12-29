# 📚 PHP Library for Rubika bot
Botkaplus Library for rubika bots.



# Botkaplus
  <img align="center" width="200" height="200" src="https://rubika.ir/static/images/logo.svg"/>
Botkaplus Library for rubika bots.

یک کتابخانه قدرتمند و ساده برای ساخت ربات‌های روبیکا با PHP.

باتکاپلاس کتابخانه ای برای بات های روبیکا

# 📦 نصب و راه‌ اندازی

پیش نیاز

· PHP 7.4 or higher
/// · curl enable
/// · token rubika bot

# نصب
 نصب کردن فایل‌های کتابخانه
```php
composer require sinyor-ehsan/botkaplus
```

# شروع با webHook

```php
<?php

require "vendor/autoload.php";
use Botkaplus\BotClient;
use Botkaplus\Filters;
use Botkaplus\Message;

$token = "token_bot";

$bot = new BotClient($token);
// $bot->setWebhook('https://yourdomain.com/botfile.php');

$bot->onMessage(Filters::text("hello"), function(BotClient $bot, Message $message) {
        $message->replyMessage("hello from Botkaplus!");
    }
);
$bot->run();

?>
```

# شروع

```php
<?php

require "vendor/autoload.php";
use Botkaplus\BotClient;
use Botkaplus\Message;

echo "start\n";

$bot = new BotClient(token: $token);

$bot->onMessage(null, function(BotClient $bot, Message $message) {
    $message->replyMessage("**hello __from ~~[Botkaplus!](https://github.com/sinyor-ehsan/Rubika)~~__**");
});

$bot->runPolling();

?>
```


# ارسال متادیتا markdown
```php
<?php

require "vendor/autoload.php";
use Botkaplus\BotClient;
use Botkaplus\Filters;
use Botkaplus\Message;

echo "start\n";

$token = "token_bot";

$bot = new BotClient(token: $token);
// $bot->setWebhook('https://yourdomain.com/botfile.php');

$bot->onMessage(null, function(BotClient $bot, Message $message) {
    
    $text = <<<'EOT'
        Hi ##Welcome to our amazing Botkaplus! 🎉
        Here is a [Quote example](https://github.com/sinyor-ehsan/Rubika) that spans multiple lines,
        and inside it you can see:
        - __**Bold text**__
        - ~~__Italic text__~~
        - __--Underlined text--__
        - `Mono text`
        - ~~Strikethrough~~
        - ||Spoiler content||
        ##
        Outside the quote, you can also highlight:

        - **Important parts**  
        - __Emphasized words__  
        - Links like [Botkaplus](https://github.com/sinyor-ehsan/Rubika)  

        You can even show `inline code` or code blocks:

        ```<?php

        require "vendor/autoload.php";
        use Botkaplus\BotClient;
        use Botkaplus\Filters;
        use Botkaplus\Message;

        $token = "token_bot";

        $bot = new BotClient($token);

        $bot->onMessage(Filters::text("hello"), function(BotClient $bot, Message $message) {
            $message->replyMessage("hello from Botkaplus!");
            }
        );
        $bot->run();

        ?>```

        Enjoy exploring all the Markdown features! ✨
        EOT;
        
    $message->replyMessage(text:$text, parse_mode:"Markdown");
});

$bot->run();

?>
```

# ارسال متادیتا html
```php
<?php

require "vendor/autoload.php";
use Botkaplus\BotClient;
use Botkaplus\Filters;
use Botkaplus\Message;

echo "start\n";

$token = "token_bot";

$bot = new BotClient(token: $token);
// $bot->setWebhook('https://yourdomain.com/botfile.php');

$bot->onMessage(null, function(BotClient $bot, Message $message) {
    $html = <<<'HTML'
        <b>Hi 👋</b><br><br>
        Welcome to our amazing Botkaplus! 🎉<br>
        Here you can see all HTML formatting features:<br><br>
        <b>Bold text</b><br>
        <i>Italic text</i><br>
        <u>Underlined text</u><br>
        <s>Strikethrough text</s><br>
        <code>Mono text</code><br>
        <code>Inline code example</code><br>
        <pre>&lt;?php

        require "vendor/autoload.php";
        use Botkaplus\BotClient;
        use Botkaplus\Filters;
        use Botkaplus\Message;

        $token = "token_bot";

        $bot = new BotClient($token);

        $bot->onMessage(Filters::text("hello"), function(BotClient $bot, Message $message) {
            $message->replyMessage("hello from Botkaplus!");
            }
        );
        $bot->run();

        ?>
        </pre><br>
        <a href="https://github.com/sinyor-ehsan/Rubika">Link to Botkaplus</a><br>
        Emojis 😎✨🔥<br><br>
        <b>Important parts:</b><br>
        <u>Emphasized words</u><br>
        Enjoy exploring all the HTML features! 🎉
        HTML; 
    $message->replyMessage(text:$html, parse_mode:"HTML");
    
});

$bot->run();

?>
```

# ارسال متادیتا markdown با utils
```php
<?php

require "vendor/autoload.php";
use Botkaplus\BotClient;
use Botkaplus\Utils;
use Botkaplus\Message;

echo "start\n";

$token = "token_bot";

$bot = new BotClient(token: $token);
// $bot->setWebhook('https://yourdomain.com/botfile.php');

$utils = new Utils();

$bot->onMessage(null, function(BotClient $bot, Message $message) use ($utils) {
    $message->replyMessage($utils->Bold("hello " . $utils->Italic("from " . $utils->Strike($utils->Hyperlink("Botkaplus! ", "https://github.com/sinyor-ehsan/Rubika")))) . $utils->Quote("quote " . $utils->Mono("mono")));
});

$bot->run();

?>
```

# ارسال اینلاین کیبورد
```php
use Botkaplus\InlineKeypad;

$keypad = new InlineKeypad();

// ردیف اول
$keypad->addRow([
    InlineKeypad::buttonSimple("Botkaplus_1", "Botkaplus 1")
]);

// ردیف دوم
$keypad->addRow([
    InlineKeypad::buttonSimple("Botkaplus_2", "Botkaplus 2"),
    InlineKeypad::buttonSimple("Botkaplus_3", "Botkaplus 3")
]);

$inline_keypad = $keypad->build();
$message->replyMessage("send inline keypad!", inline_keypad:$inline_keypad);
```

# ارسال اینلاین Button
```php
use Botkaplus\ChatKeypad;

$chat_keypad = new ChatKeypad();

// ردیف اول
$chat_keypad->addRow([
    ChatKeypad::buttonSimple("100", "Botkaplus 1")
]);

// ردیف دوم
$chat_keypad->addRow([
    ChatKeypad::buttonSimple("101", "Botkaplus 2"),
    ChatKeypad::buttonSimple("102", "Botkaplus 3")
]);

$chat_keypad->setResizeKeyboard(true);
$chat_keypad->setOnTimeKeyboard(true);

$chat_keypad = $chat_keypad->build();
$message->replyMessage("send chat keypad!", chat_keypad:$chat_keypad);
```


# 🧩 1. ساخت کیبورد Inline
مثال کامل InlineKeypad
```php
use Botkaplus\InlineKeypad;

$keypad = new InlineKeypad();

// ردیف اول
$keypad->addRow([
    InlineKeypad::buttonSimple("Botkaplus_1", "Botkaplus 1")
]);

// ردیف دوم
$keypad->addRow([
    InlineKeypad::buttonSimple("Botkaplus_2", "Botkaplus 2"),
    InlineKeypad::buttonSimple("Botkaplus_3", "Botkaplus 3")
]);

$linkBtn = InlineKeypad::buttonUrlLink(id: "link",title: "ورود به سایت", url: "https://example.com");

$join_button = InlineKeypad::buttonJoinChannelData(id: "join_button", text:"کانال ما", username:"Botkaplus");

$button_open_chat = InlineKeypad::buttonOpenChat(id:"open_chat", text:"باز کردن چت", object_guid:"u0aaaa", object_type:"User");

// $keypad->addRow($linkBtn);

$inline_keypad = $keypad->build();

// ارسال پیام همراه با کیبورد
$message->replyMessage("Inline keypad example", inline_keypad: $inline_keypad);
```


## - 🔄 ارسال همه دکمه‌ها به‌صورت InlineKeypad
در Botkaplus، تقریباً تمام دکمه‌های ChatKeypad (به‌جز چند مورد خاص) می‌توانند به‌صورت Inline نیز ارسال شوند.

# 🧩 2. ساخت کیبورد Chat (پیشرفته)
کیبورد Chat شامل انواع دکمه‌های تعاملی است:
- انتخاب (Selection)
- تقویم (Calendar)
- انتخاب عدد (Number Picker)
- انتخاب رشته (String Picker)
- انتخاب موقعیت (Location Picker)
- ورودی متنی (Textbox)
- ارسال فایل، عکس، ویدیو، صوت
- درخواست شماره/موقعیت کاربر
- لینک
- بارکد
مثال کامل ChatKeypad
```php
use Botkaplus\ChatKeypad;

$keypad = new ChatKeypad();

// آیتم‌های انتخاب
$items = [
    ChatKeypad::selectionItem("سیب", "https://upload.wikimedia.org/wikipedia/commons/1/15/Red_Apple.jpg"),
    ChatKeypad::selectionItem("موز", "https://upload.wikimedia.org/wikipedia/commons/8/8a/Banana-Single.jpg")
];

// دکمه انتخاب
$button = ChatKeypad::buttonSelection(
    id: "2",
    text: "انتخاب میوه",
    selection_id: "fruit_select_1",
    title: "انتخاب میوه",
    items: $items
);

$button_simple = InlineKeypad::buttonSimple("Botkaplus_1", "Botkaplus 1");

$keypad->addRow([$button, $button_simple]);
// ساخت نهایی
$keypad = $keypad->build();
// ارسال $keypad با متد sendMessage
```

```php
use Botkaplus\ChatKeypad;
$keypad = new ChatKeypad();

// دکمه تقویم
$btn = ChatKeypad::buttonCalendar(
    id: "cal1",
    title: "انتخاب تاریخ",
    type: "DatePersian",
    default_value: "1402/01/01",
    min_year: "1390",
    max_year: "1410"
);

// دکمه انتخاب عدد
$btn_num = ChatKeypad::buttonNumberPicker(
    id: "num1",
    title: "انتخاب عدد",
    min_value: "1",
    max_value: "100",
    default_value: "10"
);

$keypad->addRow([$btn, $btn_num]);
// ساخت نهایی
$keypad = $keypad->build();
```

```php
use Botkaplus\ChatKeypad;
$keypad = new ChatKeypad();

// دکمه انتخاب رشته
$btn_str = ChatKeypad::buttonStringPicker(
    id: "pick1",
    title: "انتخاب رشته",
    items: ["PHP", "Python", "Go", "Rust"],
    default_value: "PHP"
);

// دکمه موقعیت
$btn_loc = ChatKeypad::buttonLocation(
    id: "loc1",
    type: "Picker",
    location_image_url: "https://example.com/location.png",
    default_pointer_location: ["latitude" => 35.6892, "longitude" => 51.3890],
    default_map_location: ["latitude" => 35.7000, "longitude" => 51.4000],
    title: "ارسال موقعیت"
);

$keypad->addRow([$but_str, $btn_loc]);
// ساخت نهایی
$keypad = $keypad->build();
```

```php
use Botkaplus\ChatKeypad;
$keypad = new ChatKeypad();

$btn_tbo = ChatKeypad::buttonTextbox(
    id: "txt1",
    title: "نام شما",
    type_line: "SingleLine",
    type_keypad: "String",
    place_holder: "اینجا بنویسید...",
    default_value: null
);

$btn_p = ChatKeypad::buttonPayment(
    id: "pay1",
    title: "پرداخت",
    amount: 50000,
    description: "پرداخت هزینه اشتراک"
);

// دکمه‌های رسانه
$btn_camera = ChatKeypad::buttonCameraImage(id: "cam1", title: "ارسال عکس با دوربین");
$btn_ca_v   = ChatKeypad::buttonCameraVideo(id: "camv1", title: "ارسال ویدیو با دوربین");
$btn_g_im   = ChatKeypad::buttonGalleryImage(id: "gal1", title: "انتخاب عکس از گالری");
$btn_g_vi   = ChatKeypad::buttonGalleryVideo(id: "gal2", title: "انتخاب ویدیو از گالری");
$btnFile    = ChatKeypad::buttonFile(id: "file1", title: "ارسال فایل");
$btnAudio   = ChatKeypad::buttonAudio(id: "audio1", title: "ارسال صوت");
$btnRecord  = ChatKeypad::buttonRecordAudio(id: "rec1", title: "ارسال ویس");

$btnPhone = ChatKeypad::buttonMyPhoneNumber(id: "phone1", title: "ارسال شماره من");

$btnLocation = ChatKeypad::buttonMyLocation(id: "loc1", title: "ارسال موقعیت من");

// لینک
$btnLink = ChatKeypad::buttonLink(
    id: "link1",
    title: "ورود به سایت",
    url: "https://rubika.ir"
);

$linkBtn = ChatKeypad::createLinkButton(title: "ورود به سایت", url: "https://example.com");

// درخواست شماره و موقعیت
$btnAskPhone = ChatKeypad::buttonAskMyPhoneNumber(id: "ask_phone", title: "ارسال شماره‌ام");
$btnAskLocation = ChatKeypad::buttonAskLocation(id: "ask_loc", title: "ارسال موقعیت");

// بارکد
$barcodeBtn = ChatKeypad::buttonBarcode(id: "bar1", title: "اسکن بارکد");

// اضافه کردن ردیف‌ها
$keypad->addRow([$barcodeBtn, $btn_num]);
$keypad->addRow([$btn_str, $btn_loc]);
$keypad->addRow([$barcodeBtn]);

// ساخت نهایی
$keypad = $keypad->build();

// ارسال پیام همراه با کیبورد
$message->replyMessage("**hello __from ~~[Botkaplus!](https://github.com/sinyor-ehsan/Rubika)~~__**", chat_keypad: $keypad);
```


📦 خروجی نهایی
هر دو نوع کیبورد در نهایت با متد build() ساخته می‌شوند و سپس در متد replyMessage() یا هر متد ارسال پیام (ارسال انواع فایل) دیگر قرار می‌گیرند.

✨ نکات مهم
- تمام دکمه‌ها Static Method هستند و ساختار یکپارچه دارند.
- هر ردیف با addRow() اضافه می‌شود.
- خروجی نهایی همیشه یک آرایهٔ JSON-ready است.


# ادامه ندادن به هندلرهای بعدی
```php
$bot->stopPropagation()
```

# فیلتر text
```php
$bot->onMessage(Filters::text("hello"), function(BotClient $bot, Message $message){
    $message->replyMessage("hello from Botkaplus!");
});
```

# فیلتر ترکیبی and
```php
$bot->onMessage(Filters::and(Filters::private(), Filters::command("start")), function(BotClient $bot, Message $message){
    $message->replyMessage("hello from Botkaplus to pv!");
});
```
# انواع فیلترها
```php
Filters::text("")
Filters::regex("")
Filters::command("")
Filters::chatId("")
Filters::senderId("")
Filters::buttonId("")
Filters::private()
Filters::group()
Filters::channel()
Filters::or(...)
Filters::and(...)
Filters::not(...)
```
# تنظیم کامندها
```php
$bot->setCommands([["command" => "start", "description" => "شروع ربات"], ["command" => "help", "description" => "راهنمای ربات"]]);
```

# ارسال نظرسنجی
```php
// chat_id شناسه چت مقصد
// question متن سوال
// options array[string] گزینه های سوال
// type ["Regular", "Quiz"] = "Regular" نوع
// allows_multiple_answers .کاربرد دارد "regular" فقط برای نوع e انتخاب چند گزینه
// is_anonymous باشد، رأی‌دهی ناشناس است و نام رأی‌دهندگان نمایش داده نمی‌شود true اگر 
// correct_option_index "Quiz" ایندکس گزینه درست در حالت 
// hint توضیح نظرسنجی
$bot->sendPoll(chat_id:$bot->chat_id, question:"سوال", options:["one", "two"], type:"Quiz", is_anonymous:false, correct_option_index:"0", hint:"توضیحات")
```
