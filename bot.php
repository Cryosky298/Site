<?php
ob_start();
error_reporting(0);
date_default_timezone_set('Asia/Tashkent');

/*
<---- @taki_animora ---->
================================================================================
 BOT HAQIDA MA'LUMOT
================================================================================
 Yaratuvchi: @taki_animora
 Dasturlash tili: PHP
 Ma'lumotlar bazasi: MySQL
--------------------------------------------------------------------------------
 ASOSIY IMKONIYATLAR:
--------------------------------------------------------------------------------
 1.  Kengaytirilgan Admin Paneli:
     - **Statistika:** Umumiy (foydalanuvchilar, animelar), yangi a'zolar (kun, hafta, oy), top animelar (ko'rilgan, saqlangan).
     - **Foydalanuvchilarni boshqarish:** ID orqali topish, banlash/bandan olish, balansni to'ldirish/ayirish, VIP status berish/olish.
     - **Ommaviy xabar:** Barcha foydalanuvchilarga xabar (forward) yuborish (multi-thread orqali).
     - **Post tayyorlash:** Anime ID si orqali post yaratib, bir yoki bir nechta kanalga yuborish.
     - **Anime boshqaruvi:** Yangi anime qo'shish, ma'lumotlarini tahrirlash, o'chirish. Qismlarni (ketma-ket yoki bittalab) qo'shish, tahrirlash va o'chirish.
     - **Kanallar:** Majburiy obuna kanallarini (oddiy, ariza orqali) boshqarish. Post uchun mo'ljallangan kanallarni qo'shish/o'chirish.
     - **Sozlamalar:**
         - Birlamchi: Valyuta, VIP narxi, studiya nomi, kontentni himoyalash (copy/forward).
         - To'lov tizimlari: Hamyonlarni qo'shish va o'chirish.
         - Matnlar: Start, qo'llanma, homiylik va konkurs matnlarini o'zgartirish.
         - Tugmalar: Asosiy menyu tugmalari nomini o'zgartirish.
     - **Konkurs:** Referal tizimiga asoslangan konkursni yoqish/o'chirish va reytingni ko'rish.
     - **Adminlar:** Bosh admin tomonidan yordamchi adminlarni qo'shish/o'chirish.
     - **Bot holati:** Botni texnik ishlar uchun vaqtincha o'chirish/yoqish.
 
 2.  Foydalanuvchi Funksiyalari:
     - **Anime qidirish:** Nomi, janri, kodi, rasmi orqali, tasodifiy anime topish.
     - **Qidiruv natijalari:** Sahifalangan (paginatsiyali) natijalar.
     - **Tomosha:**
         - "▶️ Davom ettirish": Oxirgi ko'rilgan joydan keyingi qismni ochish.
         - Barcha qismlarni sahifalangan (25 tadan) ko'rinishda ko'rish.
     - **Saqlanganlar ("Watchlist"):** Animelarni sevimlilar ro'yxatiga qo'shish, ko'rish va o'chirish.
     - **Hisob:** Balansni ko'rish, pul kiritish uchun rekvizitlarni olish.
     - **VIP Status:** Obuna sotib olish va muddatini uzaytirish.
     - **Konkurs:** Referal havola orqali do'stlarni taklif qilish va reytingda qatnashish.
     - **Fikr bildirish:** Adminlarga anonim xabar yuborish.
 
 3.  Texnik Imkoniyatlar:
     - **Majburiy obuna:** Kanallarga obunani tekshirish (oddiy va ariza (`join_request`) orqali).
     - **Avto-xabarnoma:** Animega yangi qism qo'shilganda uni "Saqlanganlar"ga qo'shgan foydalanuvchilarga avtomatik bildirishnoma yuborish.
     - **Xavfsizlik:** SQL Injection'dan himoyalanish uchun `mysqli_real_escape_string` va tayyorlangan so'rovlardan foydalanish.
     - **Himoya:** Kontentni nusxalash va forward qilishni cheklash (`protect_content`).
     - **Optimallashtirish:** Ommaviy xabar yuborish uchun `cURL multi-exec` dan foydalanish.
================================================================================
*/

$bot_token = "8476948319:AAFC_QqOOUtCJ_GiLDkGalFW3qa16ksWvO0"; // bot token

define('API_KEY',$bot_token);
$bot_info = json_decode(file_get_contents("https://api.telegram.org/bot".API_KEY."/getMe"));
if (!$bot_info || !$bot_info->ok) {
    // Agar bot ma'lumotlarini ololmasa, skriptni to'xtatish
    die("Xatolik: Telegram API ga ulanib bo'lmadi yoki bot tokeni noto'g'ri.");
}
$bot = $bot_info->result->username;

$taki_animora = "7483732504"; // admin_id
$admins = file_get_contents("admin/admins.txt");
$admin = explode("\n",$admins);
$studio_name = file_get_contents("admin/studio_name.txt");
$admin[] = $taki_animora;
$user = file_get_contents("admin/user.txt");
$soat = date('H:i');
$sana = date("d.m.Y");

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$uri = dirname($_SERVER['REQUEST_URI']);
$web_urlis = "$protocol://$host$uri/animes.php";

require ("sql.php");
require 'ManhwaManager.php';

function multi_curl_forward($user_ids, $from_chat_id, $message_id) {
    global $bot_token;
    $mh = curl_multi_init();
    $handles = [];

    foreach ($user_ids as $user_id) {
        $params = [
            'chat_id' => $user_id,
            'from_chat_id' => $from_chat_id,
            'message_id' => $message_id
        ];
        $url = "https://api.telegram.org/bot" . $bot_token . "/forwardMessage?" . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running > 0);

    foreach ($handles as $ch) {
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
}

function multi_curl_send($user_ids, $text) {
    global $bot_token;
    $mh = curl_multi_init();
    $handles = [];
    $sent_count = 0;

    foreach ($user_ids as $user_id) {
        $params = [
            'chat_id' => $user_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage?" . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running > 0);

    foreach ($handles as $ch) {
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
}

function getAdmin($chat){
$url = "https://api.telegram.org/bot".API_KEY."/getChatAdministrators?chat_id=@".$chat;
$result = file_get_contents($url);
$result = json_decode ($result);
return $result->ok;
}

function deleteFolder($path){
if(is_dir($path) === true){
$files = array_diff(scandir($path), array('.', '..'));
foreach ($files as $file)
deleteFolder(realpath($path) . '/' . $file);
return rmdir($path);
}else if (is_file($path) === true)
return unlink($path);
return false;
}

function joinchat($userId, $key = null) {
    global $connect, $status, $bot, $admin;

    // Admin va VIP foydalanuvchilar tekshiruvdan o'tkazilmaydi
    if ($status === 'VIP' || in_array($userId, $admin)) {
        return true;
    }

    $userId = strval($userId);
    $query = $connect->query("SELECT channelId, channelType, channelLink FROM channels");
    if ($query->num_rows === 0) {
        return true; // Agar majburiy kanallar yo'q bo'lsa, ruxsat berish
    }

    $channels = $query->fetch_all(MYSQLI_ASSOC);
    $noSubs = 0;
    $buttons = [];

    foreach ($channels as $channel) {
        $channelId = $channel['channelId'];
        $channelLink = $channel['channelLink'];

        try {
            $chatMember = bot('getChatMember', [
                'chat_id' => $channelId,
                'user_id' => $userId
            ]);

            // Foydalanuvchi a'zo, admin yoki yaratuvchi emasligini tekshirish
            if (!in_array($chatMember->result->status, ['member', 'administrator', 'creator'])) {
                $noSubs++;
                $chatInfo = bot('getChat', ['chat_id' => $channelId]);
                $channelTitle = $chatInfo->result->title ?? "Kanal";
                $buttons[] = [
                    'text' => $channelTitle,
                    'url'  => $channelLink
                ];
            }
        } catch (Exception $e) {
            // Agar API so'rovida xatolik bo'lsa (masalan, bot kanalda admin emas), keyingi kanalga o'tish
            continue;
        }
    }

    if ($noSubs > 0) {
        $insta = get('admin/instagram.txt');
        $youtube = get('admin/youtube.txt');

        if (!empty($insta)) {
            $buttons[] = ['text' => "📸 Instagram", 'url' => $insta];
        } elseif (!empty($youtube)) {
            $buttons[] = ['text' => "📺 YouTube", 'url' => $youtube];
        }

        // Agar foydalanuvchi anime ID si bilan kelgan bo'lsa, uni saqlab qolish
        $start_key = $key ?: "default"; // "check" o'rniga umumiyroq nom
        $buttons[] = ['text' => "✅ Tekshirish", 'url' => "https://t.me/$bot?start=$start_key"];

        return false; // Obuna bo'lmaganini bildirish
    }
    
    // Agar tekshiruvdan o'tsa va start kaliti bo'lsa, uni tozalash
    if ($key) {
        bot('sendMessage', ['chat_id' => $userId, 'text' => "✅ Obunangiz tasdiqlandi!", 'reply_markup' => json_encode(['remove_keyboard' => true])]);
    }
     
    return true;
}



function accl($d,$s,$j=false){
return bot('answerCallbackQuery',[
'callback_query_id'=>$d,
'text'=>$s,
'show_alert'=>$j
]);
}

function del(){
global $cid,$mid,$cid2,$mid2;
return bot('deleteMessage',[
'chat_id'=>$cid2.$cid,
'message_id'=>$mid2.$mid,
]);
}


function edit($id,$mid,$tx,$m){
return bot('editMessageText',[
'chat_id'=>$id,
'message_id'=>$mid,
'text'=>$tx,
'parse_mode'=>"HTML",
'disable_web_page_preview'=>true,
'reply_markup'=>$m,
]);
}



function sms($id,$tx,$m){
return bot('sendMessage',[
'chat_id'=>$id,
'text'=>$tx,
'parse_mode'=>"HTML",
'disable_web_page_preview'=>true,
'reply_markup'=>$m,
]);
}



function get($h){
return file_get_contents($h);
}

function put($h,$r){
file_put_contents($h,$r);
}

function bot($method,$datas=[]){
	$url = "https://api.telegram.org/bot".API_KEY."/".$method;
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$datas);
	$res = curl_exec($ch);
	if(curl_error($ch)){
		var_dump(curl_error($ch));
	}else{
		return json_decode($res);
	}
}


function containsEmoji($string) {
	// Emoji Unicode diapazonlarini belgilash
	$emojiPattern = '/[\x{1F600}-\x{1F64F}]/u'; // Emotikonlar
	$emojiPattern .= '|[\x{1F300}-\x{1F5FF}]'; // Belgilar va piktograflar
	$emojiPattern .= '|[\x{1F680}-\x{1F6FF}]'; // Transport va xaritalar
	$emojiPattern .= '|[\x{1F700}-\x{1F77F}]'; // Alkimyo belgilar
	$emojiPattern .= '|[\x{1F780}-\x{1F7FF}]'; // Har xil belgilar
	$emojiPattern .= '|[\x{1F800}-\x{1F8FF}]'; // Suv belgilari
	$emojiPattern .= '|[\x{1F900}-\x{1F9FF}]'; // Odatdagilar
	$emojiPattern .= '|[\x{1FA00}-\x{1FA6F}]'; // Qisqichbaqasimon belgilar
	$emojiPattern .= '|[\x{2600}-\x{26FF}]';   // Turli xil belgilar va piktograflar
	$emojiPattern .= '|[\x{2700}-\x{27BF}]';   // Dingbatlar
	$emojiPattern .= '/u';
 
	// Regex orqali tekshirish
	return preg_match($emojiPattern, $string) === 1;
}

function removeEmoji($string) {
	$emojiPattern = '/[\x{1F600}-\x{1F64F}]/u'; // Emotikonlar
	$emojiPattern .= '|[\x{1F300}-\x{1F5FF}]'; // Belgilar va piktograflar
	$emojiPattern .= '|[\x{1F680}-\x{1F6FF}]'; // Transport va xaritalar
	$emojiPattern .= '|[\x{1F700}-\x{1F77F}]'; // Alkimyo belgilar
	$emojiPattern .= '|[\x{1F780}-\x{1F7FF}]'; // Har xil belgilar
	$emojiPattern .= '|[\x{1F800}-\x{1F8FF}]'; // Suv belgilari
	$emojiPattern .= '|[\x{1F900}-\x{1F9FF}]';
	$emojiPattern .= '|[\x{1FA00}-\x{1FA6F}]';
	$emojiPattern .= '|[\x{2600}-\x{26FF}]';  
	$emojiPattern .= '|[\x{2700}-\x{27BF}]';   
	$emojiPattern .= '/u';
 
	return preg_replace($emojiPattern, '', $string);
}

$nurillayev = json_decode(file_get_contents('php://input'));

if (isset($nurillayev->chat_join_request)) {
    $chatId = $nurillayev->chat_join_request->chat->id;
    $userId = $nurillayev->chat_join_request->from->id;

    $check = $connect->query("SELECT * FROM joinRequests WHERE channelId = '$chatId' AND userId = '$userId'");
    if ($check->num_rows === 0) {
        $connect->query("INSERT INTO joinRequests (channelId, userId) VALUES ('$chatId', '$userId')");
    }
    exit(); // Ariza qabul qilingandan so'ng skriptni to'xtatish
}

if (isset($nurillayev->my_chat_member)) {
    $botdel = $nurillayev->my_chat_member->new_chat_member; 
    $botdelid = $nurillayev->my_chat_member->from->id; 
    $userstatus= $botdel->status; 
    // Bu yerda botning guruhdagi statusi o'zgarganda bajariladigan amallar bo'lishi mumkin
    // Hozircha bo'sh, lekin kelajakda kerak bo'lishi mumkin
    exit();
}

$nurillayev = json_decode(file_get_contents('php://input'));
$message = $nurillayev->message;
$cid = $message->chat->id;
$name = $message->chat->first_name;
$tx = $message->text;
$step = file_get_contents("step/$cid.step");
$steps = file_get_contents("steps/$cid.steps");
$mid = $message->message_id;
$type = $message->chat->type;
$text = $message->text;
$uid= $message->from->id;
$name = $message->from->first_name;
$familya = $message->from->last_name;
$bio = $message->from->about;
$username = $message->from->username;
$chat_id = $message->chat->id;
$message_id = $message->message_id;
$reply = $message->reply_to_message->text;
$nameru = "<a href='tg://user?id=$uid'>$name $familya</a>";

//inline uchun metodlar
$data = $nurillayev->callback_query->data;
$qid = $nurillayev->callback_query->id;
$id = $nurillayev->inline_query->id;
$query = $nurillayev->inline_query->query;
$query_id = $nurillayev->inline_query->from->id;
$cid2 = $nurillayev->callback_query->message->chat->id;
$mid2 = $nurillayev->callback_query->message->message_id;
$callfrid = $nurillayev->callback_query->from->id;
$callname = $nurillayev->callback_query->from->first_name;
$calluser = $nurillayev->callback_query->from->username;
$surname = $nurillayev->callback_query->from->last_name;
$about = $nurillayev->callback_query->from->about;
$nameuz = "<a href='tg://user?id=$callfrid'>$callname $surname</a>";

if(isset($data)){
$chat_id=$cid2;
$message_id=$mid2;
}

$photo = $message->photo;
$file = $photo[count($photo)-1]->file_id;

//tugmalar
if(file_get_contents("tugma/key1.txt")){
	}else{
		if(file_put_contents("tugma/key1.txt","🔎 Anime izlash"));
	}
if(file_get_contents("tugma/key2.txt")){
	}else{
		if(file_put_contents("tugma/key2.txt","💎 VIP"));
	}
if(file_get_contents("tugma/key3.txt")){
	}else{
		if(file_put_contents("tugma/key3.txt","💰 Hisobim"));
	}
if(file_get_contents("tugma/key4.txt")){
	}else{
		if(file_put_contents("tugma/key4.txt","➕ Pul kiritish"));
	}
if(file_get_contents("tugma/key5.txt")){
	}else{
		if(file_put_contents("tugma/key5.txt","📚 Qo'llanma"));
	}
if(file_get_contents("tugma/key6.txt")){
	}else{
		if(file_put_contents("tugma/key6.txt","💵 Reklama va Homiylik"));
	}
if(file_get_contents("tugma/key7.txt")){
	}else{
		if(file_put_contents("tugma/key7.txt","🏆 Konkurs"));
	}
if(file_get_contents("tugma/key8.txt")){
	}else{
		if(file_put_contents("tugma/key8.txt","🎲 Tasodifiy anime"));
	}
if(file_get_contents("tugma/key9.txt")){
	}else{
		if(file_put_contents("tugma/key9.txt","✍️ Fikr bildirish"));
	}

	
//pul va referal sozlamalar

if(file_get_contents("admin/valyuta.txt")){
	}else{
		if(file_put_contents("admin/valyuta.txt","so'm"));
}

if(file_get_contents("admin/vip.txt")){
	}else{
		if(file_put_contents("admin/vip.txt","25000"));
}

if(file_get_contents("admin/holat.txt")){
	}else{
		if(file_put_contents("admin/holat.txt","Yoqilgan"));
}

if(file_exists("admin/anime_kanal.txt")==false){
file_put_contents("admin/anime_kanal.txt","@username");
}
if(file_exists("tizim/content.txt")==false){
file_put_contents("tizim/content.txt","false");
}

// Konkurs uchun fayl
if(file_exists("admin/konkurs_holati.txt")==false){
    file_put_contents("admin/konkurs_holati.txt","off");
}

//matnlar
if(file_get_contents("matn/start.txt")){
}else{
if(file_put_contents("matn/start.txt","✨"));
}

$key1 = file_get_contents("tugma/key1.txt");
$key2 = file_get_contents("tugma/key2.txt");
$key3 = file_get_contents("tugma/key3.txt");
$key4 = file_get_contents("tugma/key4.txt");
$key5 = file_get_contents("tugma/key5.txt");
$key6 = file_get_contents("tugma/key6.txt");
$key7 = file_get_contents("tugma/key7.txt");
$key8 = file_get_contents("tugma/key8.txt");
$key9 = file_get_contents("tugma/key9.txt");

$test = file_get_contents("step/test.txt");
$test1 = file_get_contents("step/test1.txt");
$test2 = file_get_contents("step/test2.txt");
$turi = file_get_contents("tizim/turi.txt");
$anime_kanal = file("admin/anime_kanal.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$narx = file_get_contents("admin/vip.txt");
$kanal = file_get_contents("admin/kanal.txt");
$valyuta = file_get_contents("admin/valyuta.txt");
$start = str_replace(["%first%","%id%","%botname%","%hour%","%date%"], [$name,$cid,$bot,$soat,$sana],file_get_contents("matn/start.txt"));
$qollanma = str_replace(["%first%","%id%","%hour%","%date%","%user%","%botname%",], [$name,$cid,$soat,$sana,$user,$bot],file_get_contents("matn/qollanma.txt"));
$photo = file_get_contents("matn/photo.txt");
$homiy = file_get_contents("matn/homiy.txt");
$holat = file_get_contents("admin/holat.txt");
$content = get("tizim/content.txt");

mkdir("tizim");
mkdir("step");
mkdir("admin");
mkdir("tugma");
mkdir("matn");

// ==================== FOYDALANUVCHI MA'LUMOTLARINI OLISH (OPTIMIZATSIYA) ====================
$user_data = [];
if ($chat_id) {
    $user_id_safe = (int)$chat_id;
    $user_query = mysqli_query($connect, 
        "SELECT u.user_id, u.status, u.refid, u.sana as reg_sana, k.pul, k.pul2, k.odam, k.ban 
         FROM user_id u 
         LEFT JOIN kabinet k ON u.user_id = k.user_id 
         WHERE u.user_id = $user_id_safe"
    );
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $user_data = mysqli_fetch_assoc($user_query);
    }
}

// O'zgaruvchilarni o'rnatish
$user_id = $user_data['user_id'] ?? null;
$status = $user_data['status'] ?? 'Oddiy';
$taklid_id = $user_data['refid'] ?? null;
$usana = $user_data['reg_sana'] ?? null;
$pul = $user_data['pul'] ?? 0;
$pul2 = $user_data['pul2'] ?? 0;
$odam = $user_data['odam'] ?? 0;
$ban = $user_data['ban'] ?? 'unban';

$panel = json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"*️⃣ Birlamchi sozlamalar"]],
[['text'=>"📊 Statistika"],['text'=>"✉ Xabar Yuborish"]],
[['text'=>"📬 Post tayyorlash"],['text'=>"🏆 Konkurs"]],
[['text'=>"🎥 Animelar sozlash"],['text'=>"📚 Manhwa Boshqaruvi"]],
[['text'=>"🔎 Foydalanuvchini boshqarish"]],
[['text'=>"📢 Kanallar"],['text'=>"🎛 Tugmalar"],['text'=>"📃 Matnlar"]],
[['text'=>"📋 Adminlar"],['text'=>"🤖 Bot holati"]],
[['text'=>"◀️ Orqaga"]]
]
]);

$asosiy = $panel;

$menu = json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
    [['text'=>"$key1"], ['text'=>"$key8"]], // 🔎 Anime izlash | 🎲 Tasodifiy anime
    [['text'=>"$key7"],['text'=>"$key3"]], // 🏆 Konkurs | 💰 Hisobim
    [['text'=>"❤️ Saqlanganlar"]],
    [['text'=>"$key2"],['text'=>"$key4"]], // 💎 VIP | ➕ Pul kiritish
    [['text'=>"$key5"],['text'=>"$key6"],['text'=>"$key9"]], // 📚 Qo'llanma | 💵 Reklama va Homiylik | ✍️ Fikr bildirish
]
]);

$menus = json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
    [['text'=>"$key1"], ['text'=>"$key8"]], // 🔎 Anime izlash | 🎲 Tasodifiy anime
    [['text'=>"$key7"],['text'=>"$key3"]], // 🏆 Konkurs | 💰 Hisobim
    [['text'=>"❤️ Saqlanganlar"]],
    [['text'=>"$key2"],['text'=>"$key4"]], // 💎 VIP | ➕ Pul kiritish
    [['text'=>"$key5"],['text'=>"$key6"],['text'=>"$key9"]], // 📚 Qo'llanma | 💵 Reklama va Homiylik | ✍️ Fikr bildirish
    [['text'=>"🗄 Boshqarish"]],
]
]);

$back = json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"◀️ Orqaga"]],
]
]);

$boshqarish = json_encode([
'resize_keyboard'=>true,
'keyboard'=>[
[['text'=>"🗄 Boshqarish"]],
]
]);

if(in_array($cid,$admin)){
$menyu = $menus;
}else{
$menyu = $menu;
}

if(in_array($cid2,$admin)){
$menyus = $menus;
}else{
$menyus = $menu;
}

//<---- @taki_animora ---->//
if($text){
if($ban == "ban"){
exit();
}
}

if($data){
$ban = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = " . (int)$cid2))['ban'];
	if($ban == "ban"){
	exit();
}
}

if(isset($message)){
if(!$connect){
bot('sendMessage',[
'chat_id' =>$cid,
'text'=>"⚠️ <b>Xatolik!</b>

<i>Botdan ro'yxatdan o'tish uchun, /start buyrug'ini yuboring!</i>",
'parse_mode' =>'html',
]);
exit();
}
}

if($text){
 if($holat == "O'chirilgan"){
	if(in_array($cid,$admin)){
}else{
	bot('sendMessage',[
	'chat_id'=>$cid,
	'text'=>"⛔️ <b>Bot vaqtinchalik o'chirilgan!</b>

<i>Botda ta'mirlash ishlari olib borilayotgan bo'lishi mumkin!</i>",
'parse_mode'=>'html',
]);
exit();
}
}
}

if($data){
 if($holat == "O'chirilgan"){
	if(in_array($cid2,$admin)){
}else{
	bot('answerCallbackQuery',[
		'callback_query_id'=>$qid,
		'text'=>"⛔️ Bot vaqtinchalik o'chirilgan!

Botda ta'mirlash ishlari olib borilayotgan bo'lishi mumkin!",
		'show_alert'=>true,
		]);
exit();
}
}
}

if(isset($message)){
$result = mysqli_query($connect,"SELECT user_id FROM user_id WHERE user_id = " . (int)$cid);
$is_new_user = (mysqli_num_rows($result) == 0);

if($is_new_user){
    $ref_id = null;
    if (strpos($text, "/start ") === 0) {
        $ref_id = str_replace("/start ", "", $text);
        if (!is_numeric($ref_id) || $ref_id == $cid) { // O'z-o'ziga referal bo'lishni oldini olish
            $ref_id = null;
        }
    }

    if ($ref_id) {
        mysqli_query($connect,"INSERT INTO user_id(`user_id`,`status`,`sana`, `refid`) VALUES ('" . (int)$cid . "','Oddiy','$sana', '" . (int)$ref_id . "')");
        $konkurs_holati = file_get_contents("admin/konkurs_holati.txt");
        if ($konkurs_holati == "on") {
            mysqli_query($connect, "UPDATE kabinet SET odam = odam + 1 WHERE user_id = '" . (int)$ref_id . "'");
        }
    } else {
        mysqli_query($connect,"INSERT INTO user_id(`user_id`,`status`,`sana`) VALUES ('" . (int)$cid . "','Oddiy','$sana')");
    }
}
}

if(isset($message)){
$result = mysqli_query($connect,"SELECT user_id FROM kabinet WHERE user_id = " . (int)$cid);
$row = mysqli_fetch_assoc($result);
if(!$row){
mysqli_query($connect,"INSERT INTO kabinet(`user_id`,`pul`,`pul2`,`odam`,`ban`) VALUES ('" . (int)$cid . "','0','0','0','unban')");
}
}

if ($text == "/start" || $text == "◀️ Orqaga" || strpos($text, "/start ") === 0) {
    $start_param = null;
    if (strpos($text, "/start ") === 0) {
        $start_param = str_replace("/start ", "", $text);
    }

    if(joinchat($cid, $start_param) == false) {
        exit();
    }

    // `joinchat` tekshiruvidan o'tgandan keyin parametrlarni qayta ishlash
    if ($start_param && strpos($start_param, "manhwa_") === 0) {
        // Manhwa uchun soxta callback_query yaratish
        $data_string = $start_param;
        $nurillayev->callback_query = new stdClass();
        $nurillayev->callback_query->message = new stdClass();
        $nurillayev->callback_query->message->chat = new stdClass();
        $nurillayev->callback_query->message->chat->id = $cid;
        $nurillayev->callback_query->data = $data_string;
        // ManhwaManager bu so'rovni o'zi qayta ishlaydi, shuning uchun skript davom etadi
    } elseif ($start_param && strpos($start_param, "check_") === 0) {
        // Anime uchun
        $anime_id = str_replace("check_", "", $start_param);
        if (is_numeric($anime_id)) {
            show_anime($cid, $anime_id);
            exit();
        }
    } else {
        // Boshqa barcha holatlar uchun (oddiy /start, "Orqaga")
        sms($cid, $start, $menyu);
        unlink("step/$cid.step");
        exit();
    }
}

if($data == "result"){
    del();
    if(joinchat($cid2)) sms($cid2,$start,$menyu);
    exit();
}


if (strpos($data, "chack=") === 0) {
    del();
    $i = 0;

    $res = bot('sendMessage', [
        'chat_id' => $cid2,
        'text' => "<b>⏳ Tekshirilmoqda... 0%</b>",
        'parse_mode' => 'html'
    ]);

    $messing_id2 = $res->result->message_id;

    $bars = ["░░░░░░", "█░░░░░", "██░░░░", "███░░░", "████░░", "█████░", "██████"]; //NOSONAR
    foreach ($bars as $index => $bar) {
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $messing_id2,
            'text' => "<b>⏳ Tekshirilmoqda... " . ($index * 15) . "%\n$bar</b>",
            'parse_mode' => 'html',
        ]);
        usleep(400000); 
    }

    bot('deleteMessage', [
        'chat_id' => $cid2,
        'message_id' => $messing_id2
    ]);

    $id = str_replace("chack=", "", $data);
    if(joinchat($cid2, $id)){
        // joinchat() funksiyasi muvaffaqiyatsiz bo'lsa, o'zi xabar yuborib, skriptni to'xtatadi. //NOSONAR
        // Agar kod bu yerga yetib kelsa, demak obuna tasdiqlangan.
        del();
        show_anime($cid2, $id);
    }
    
    exit();
}




//<---- @taki_animora ---->//

if ($text == "/help") {
    sms($cid, "ℹ Foydalanish uchun buyrug‘ingizni kiriting.", null);
    exit();
}

if (mb_stripos($text, "/start ") !== false && $text != "/start anipass") {
    $id = str_replace('/start ', '', $text);
    if(joinchat($cid, (int)$id) == false) exit();
    $safe_id = (int)$id;
    $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT id FROM animelar WHERE id = $safe_id"));
    if ($rew) {
        show_anime($cid, $safe_id);
    } else {
        sms($cid, $start, $menyu); // Agar ID topilmasa, oddiy start menyusini ko'rsatish
    }
}

if (strpos($data, "anime=") === 0) {
    del();
    $id = str_replace("anime=", "", $data);
    if (is_numeric($id)) show_anime($cid2, (int)$id);
}

function show_anime($cid, $id) {
    global $connect, $anime_kanal, $content;
    if (joinchat($cid, (int)$id) == false) return;
    // del(); // Keraksiz xabarlarni o'chirish, bu foydalanuvchi menyusini ham o'chirib yuborishi mumkin

    $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM animelar WHERE id = " . (int)$id));

    if (!$rew) {
        sms($cid, "❌ Ma'lumot topilmadi.");
        return;
    }

    $file_id = $rew['rams'];
    $first_char = isset($file_id[0]) ? strtoupper($file_id[0]) : '';

    $media_type = ($first_char == 'B') ? 'sendVideo' : 'sendPhoto';
    $media_key = ($first_char == 'B') ? 'video' : 'photo';

    $cs = (int)$rew['qidiruv'] + 1;
    mysqli_query($connect, "UPDATE animelar SET qidiruv = $cs WHERE id = " . (int)$id);

    // "Tomoshani davom ettirish" tugmasini yaratish
    $continue_button = null;
    $last_watched = get_watch_progress($cid, $id);
    if ($last_watched !== null) {
        $next_episode = $last_watched + 1;
        // Keyingi qism mavjudligini tekshirish
        $next_ep_check = mysqli_query($connect, "SELECT id FROM anime_datas WHERE id = " . (int)$id . " AND qism = " . (int)$next_episode);
        if (mysqli_num_rows($next_ep_check) > 0) {
            $continue_button = ['text' => "▶️ Davom ettirish ($next_episode)", 'callback_data' => "yuklanolish=$id=$next_episode=$last_watched"];
        }
    }

    $inline_keyboard = [
        [['text' => "📥 Barcha qismlar", 'callback_data' => "yuklanolish=" . (int)$id . "=1"]],
        [['text' => "❤️ Saqlab qo‘yish", 'callback_data' => "watchlist_add=" . (int)$id]],
    ];

    if ($continue_button) {
        array_unshift($inline_keyboard, [$continue_button]); // Tugmani ro'yxat boshiga qo'shish
    }

    bot($media_type, [
        'chat_id' => $cid,
        $media_key => $file_id,
        'caption' => "<b>🎬 Nomi: {$rew['nom']}</b>\n
🎥 Qismi: {$rew['qismi']}
🌍 Davlati: {$rew['davlat']}
🇺🇿 Tili: {$rew['tili']}
📆 Yili: {$rew['yili']}
🎞 Janri: {$rew['janri']}

🔍 Qidirishlar soni: $cs

🍿 {$anime_kanal[0]}",
        'parse_mode' => "html",
        'reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard]),
        'protect_content' => $content,
    ]);
}

function update_watch_progress($user_id, $anime_id, $episode_number) {
    global $connect;
    $user_id = (int)$user_id;
    $anime_id = (int)$anime_id;
    $episode_number = (int)$episode_number;

    if ($user_id > 0 && $anime_id > 0 && $episode_number > 0) {
        $sql = "INSERT INTO user_watch_progress (user_id, anime_id, last_episode) 
                VALUES ($user_id, $anime_id, $episode_number)
                ON DUPLICATE KEY UPDATE last_episode = $episode_number";
        mysqli_query($connect, $sql);
    }
}

function get_watch_progress($user_id, $anime_id) {
    global $connect;
    $user_id = (int)$user_id;
    $anime_id = (int)$anime_id;

    $sql = "SELECT last_episode FROM user_watch_progress WHERE user_id = $user_id AND anime_id = $anime_id";
    $result = mysqli_query($connect, $sql);

    if ($row = mysqli_fetch_assoc($result)) {
        return $row['last_episode'];
    }
    return null;
}

if ($data == "close")
	del();

if ($text == $key8 and joinchat($cid)) {
    $random_anime = mysqli_fetch_assoc(mysqli_query($connect, "SELECT id FROM animelar ORDER BY RAND() LIMIT 1"));
    if ($random_anime) {
        show_anime($cid, $random_anime['id']);
    } else {
        sms($cid, "😔 Afsus, botda hali animelar mavjud emas.", null);
    }
    exit();
}
if ($data == "close")
	del();

if ($text == $key1 and joinchat($cid)) {
    if(joinchat($cid) == false) exit();
	sms($cid, "<b>🔍Qidiruv tipini tanlang :</b>", json_encode([
		'inline_keyboard' => [
			[['text' => "🏷️ Anime nomi orqali", 'callback_data' => "searchByName"], ['text' => "⏱️ So'ngi yuklanganlar", 'callback_data' => "lastUploads"]],
			[['text' => "💬Janr orqali qidirish", 'callback_data' => "searchByGenre"]],
			[['text' => "⭐ Eng ko'p saqlangan", 'callback_data' => "topWatchlist"]],
			[['text' => "📌Kod orqali", 'callback_data' => "searchByCode"], ['text' => "👁️ Eng ko'p ko'rilgan", 'callback_data' => "topViewers"]],
			[['text'=>"🖼️Rasm orqali qidirish",'callback_data'=>"searchByImage"]],
			[['text' => "🌐 Web Animes", 'web_app' => ['url' => $web_urlis]]],
			
			[['text' => "📚Barcha animelar", 'callback_data' => "allAnimes"]]
		]
	]));
	exit();
}

if ($data == "topWatchlist") {
    if(joinchat($cid2) == false) exit();
    $query = mysqli_query($connect, "
        SELECT w.anime_id, a.nom, COUNT(w.anime_id) AS save_count
        FROM user_watchlist w
        JOIN animelar a ON w.anime_id = a.id
        GROUP BY w.anime_id, a.nom
        ORDER BY save_count DESC
        LIMIT 10
    ");

    if (mysqli_num_rows($query) > 0) {
        $text = "<b>⭐ Eng ko'p saqlangan animelar (Top 10):</b>\n\n";
        $i = 1;
        $buttons = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $buttons[] = [['text' => "{$i}. {$row['nom']} (❤️ {$row['save_count']})", 'callback_data' => "anime=" . $row['anime_id']]];
            $i++;
        }
        edit($cid2, $mid2, $text, json_encode(['inline_keyboard' => $buttons]));
    } else {
        accl($qid, "Hali hech qaysi anime saqlanmagan.", true);
    }
    exit();
}

function show_search_results($chat_id, $search_query, $page = 1, $message_id = null, $search_type = 'name') {
    global $connect;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search_query_safe = mysqli_real_escape_string($connect, $search_query);

    $count_sql = "";
    $results_sql = "";

    if ($search_type == 'name') {
        $count_sql = "SELECT COUNT(*) as total FROM animelar WHERE nom LIKE '%$search_query_safe%'";
        $results_sql = "SELECT id, nom FROM animelar WHERE nom LIKE '%$search_query_safe%' LIMIT $limit OFFSET $offset";
    } elseif ($search_type == 'genre') {
        $count_sql = "SELECT COUNT(*) as total FROM animelar WHERE janri LIKE '%$search_query_safe%'";
        $results_sql = "SELECT id, nom FROM animelar WHERE janri LIKE '%$search_query_safe%' LIMIT $limit OFFSET $offset";
    }

    $count_query_res = mysqli_query($connect, $count_sql);
    $total_items = mysqli_fetch_assoc($count_query_res)['total'];
    $total_pages = ceil($total_items / $limit);

    if ($total_items == 0) {
        sms($chat_id, "<b>😔 Afsus, \"$search_query\" bo'yicha hech narsa topilmadi.</b>", null);
        return;
    }

    $result = mysqli_query($connect, $results_sql);
    $buttons = [];
    $text_response = "<b>🔍 \"$search_query\" uchun qidiruv natijalari:</b>";
    $i = $offset + 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $buttons[] = [['text' => "{$i}. {$row['nom']}", 'callback_data' => "anime={$row['id']}"]];
        $i++;
    }

    // Pagination buttons
    $pagination_buttons = [];
    if ($page > 1) {
        $prev_page = $page - 1;
        $pagination_buttons[] = ['text' => "⬅️ Oldingi", 'callback_data' => "search_page=$search_type=$prev_page=" . urlencode($search_query)];
    }
    if ($total_pages > 1) {
        $pagination_buttons[] = ['text' => "$page/$total_pages", 'callback_data' => "null"];
    }
    if ($page < $total_pages) {
        $next_page = $page + 1;
        $pagination_buttons[] = ['text' => "Keyingi ➡️", 'callback_data' => "search_page=$search_type=$next_page=" . urlencode($search_query)];
    }
    if (!empty($pagination_buttons)) {
        $buttons[] = $pagination_buttons;
    }

    if ($message_id) {
        edit($chat_id, $message_id, $text_response, json_encode(['inline_keyboard' => $buttons]));
    } else {
        sms($chat_id, $text_response, json_encode(['inline_keyboard' => $buttons]));
    }
}

if ($data == "searchByName") {
    del();
    sms($cid2, "<b>Anime nomini yuboring:</b>", $back);
    put("step/$cid2.step", "searchByName_wait");
    exit();
}

if ($step == "searchByName_wait" and isset($text)) {
    unlink("step/$cid.step");
    show_search_results($cid, $text, 1, null, 'name');
    exit();
}

if (strpos($data, "search_page=") === 0) {
    list(, $type, $page, $query) = explode("=", $data, 4);
    show_search_results($cid2, urldecode($query), (int)$page, $mid2, $type);
    exit();
}

if ($data == "lastUploads") {
    if(joinchat($cid2) == false) exit();
	if ($status == "VIP") {
		$a = $connect->query("SELECT * FROM `animelar` ORDER BY `sana` DESC LIMIT 0,10");
		$i = 1;
		while ($s = mysqli_fetch_assoc($a)) {
			$uz[] = ['text' => "$i - $s[nom]", 'callback_data' => "anime=$s[id]"];
		}
		$keyboard2 = array_chunk($uz, 1);
		$kb = json_encode([
			'inline_keyboard' => $keyboard2,
		]);
		edit($cid2, $mid2, "<b>⬇️ Qidiruv natijalari:</b>", $kb);
		exit();
	} else {
		bot('answerCallbackQuery', [
			'callback_query_id' => $qid,
			'text' => "Ushbu funksiyadan foydalanish uchun $key2 sotib olishingiz zarur!",
			'show_alert' => true,
		]);
	}
}

if ($data == "searchByImage") {
    if(joinchat($cid2) == false) exit();
	del();
	sms($cid2, "🖼️ <b>Qidirish uchun rasm yuboring:</b>", $back);
	put("step/$cid2.step", "searchByImage_wait");
	exit();
}

if ($step == "searchByImage_wait") {
    if (isset($message->photo)) {
        $file_id = $message->photo[count($message->photo) - 1]->file_id;

        $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT id FROM animelar WHERE rams = '" . mysqli_real_escape_string($connect, $file_id) . "'"));

        if ($rew) {
            unlink("step/$cid.step");
            show_anime($cid, $rew['id']);
        } else {
            sms($cid, "😔 Ushbu rasmga mos anime topilmadi.", $back);
        }
    } else {
        sms($cid, "❌ Iltimos, faqat rasm yuboring.", $back);
    }
    exit();
}




//Rasm orqali qidirish 

if ($data == "topViewers") {
    if(joinchat($cid2) == false) exit();
    $query = mysqli_query($connect, "SELECT id, nom, qidiruv FROM animelar WHERE qidiruv > 0 ORDER BY qidiruv DESC LIMIT 10");

    if (mysqli_num_rows($query) > 0) {
        $text = "<b>👁️ Eng ko'p ko'rilgan animelar (Top 10):</b>\n\n";
        $i = 1;
        $buttons = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $text .= "<b>$i.</b> \"{$row['nom']}\" - 👁️ {$row['qidiruv']}\n";
            $buttons[] = [['text' => "$i. {$row['nom']}", 'callback_data' => "anime=" . $row['id']]];
            $i++;
        }
        edit($cid2, $mid2, $text, json_encode(['inline_keyboard' => $buttons]));
    } else {
        accl($qid, "Hali hech qaysi anime ko'rilmagan.", true);
    }
    exit();
}

if (mb_stripos($data, "yuklanolish=") !== false) {
    if (joinchat($cid2) == false) exit();

    $parts = explode("=", $data, 3);
 $anime_id = (int)$parts[1];
 $episode_number = $parts[2] ?? '1';
 $offset = is_numeric($episode_number) ? floor(((int)$episode_number - 1) / 25) * 25 : 0;
 
    $anime = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM animelar WHERE id = " . (int)$anime_id));
    $anime_name = $anime['nom'];
    $episode_data_query = mysqli_query($connect, "SELECT * FROM anime_datas WHERE id = " . (int)$anime_id . " AND qism = '" . mysqli_real_escape_string($connect, $episode_number) . "'");
    
    if (mysqli_num_rows($episode_data_query) == 0) {
        accl($qid, "❌ Ushbu qism topilmadi.", true);
        return;
    }
    $episode_data = mysqli_fetch_assoc($episode_data_query);

    // Tomosha qilish jarayonini yangilash
    update_watch_progress($cid2, $anime_id, $episode_number);

    // Asosiy anime xabarining tugmalarini o'chirish (xabarni o'zini o'chirmasdan)
    bot('editMessageReplyMarkup', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'reply_markup' => null
    ]);

    // Qismlar ro'yxati uchun tugmalar
    $buttons = [];
    $episodes = mysqli_query($connect, "SELECT * FROM anime_datas WHERE id = " . (int)$anime_id . " LIMIT $offset, 25");
    while ($row = mysqli_fetch_assoc($episodes)) {
        $qism = $row['qism'];
        if ($qism == $episode_number) {
            $buttons[] = ['text' => "📀 $qism", 'callback_data' => "null"];
        } else {
            $buttons[] = ['text' => "$qism", 'callback_data' => "yuklanolish=$anime_id=$qism"];
        }
    }
    $keyboard = array_chunk($buttons, 4);

    // Navigatsiya tugmalari
    $navigation_row = [];
    if ($offset > 0) $navigation_row[] = ['text' => "⬅️ Oldingi", 'callback_data' => "pagenation=$anime_id=" . urlencode($episode_number) . "=back"];
    $navigation_row[] = ['text' => "❌ Yopish", 'callback_data' => "close"];
    $total_episodes_q = mysqli_query($connect, "SELECT COUNT(*) as total FROM anime_datas WHERE id=".(int)$anime_id);
    $total_episodes = mysqli_fetch_assoc($total_episodes_q)['total'];
    if (($offset + 25) < $total_episodes) $navigation_row[] = ['text' => "➡️ Keyingi", 'callback_data' => "pagenation=$anime_id=" . urlencode($episode_number) . "=next"];
    
    if (in_array($cid2, $admin)) {
        $keyboard[] = [[
            'text' => "🗑 $episode_number-qismni o'chirish",
            'callback_data' => "deleteEpisode=$anime_id=" . urlencode($episode_number)
        ]];
    }

    $keyboard[] = $navigation_row;

    $kb = json_encode(['inline_keyboard' => $keyboard]);
    $caption = "<b>$anime_name</b>\n\n$episode_number-qism";

    // Fayl turiga qarab yuborish
    if ($episode_data['type'] == 'document') {
        bot('sendDocument', [
            'chat_id' => $cid2,
            'document' => $episode_data['file_id'],
            'caption' => $caption,
            'parse_mode' => 'html',
            'reply_markup' => $kb
        ]);
    } else { // 'video' (default)
        bot('sendVideo', [
        'chat_id' => $cid2,
        'video' => $episode_data['file_id'],
            'caption' => $caption,
        'parse_mode' => 'html',
        'protect_content'=>$content,
        'reply_markup' => $kb
        ]);
    }
}

if (mb_stripos($data, "pagenation=") !== false) {
    if (joinchat($cid2) == false) exit();
    $parts = explode("=", $data);
    $anime_id = (int)$parts[1]; // 1
    $current_episode = urldecode($parts[2]); // 2
    $action = $parts[3]; // 3

    // Joriy sahifani aniqlash
    $current_episode_index_q = mysqli_query($connect, "SELECT COUNT(*) as pos FROM anime_datas WHERE id = $anime_id AND CAST(qism AS UNSIGNED) < CAST('$current_episode' AS UNSIGNED)");
    $current_episode_index = mysqli_fetch_assoc($current_episode_index_q)['pos'];
    $current_page_start_index = floor($current_episode_index / 25) * 25;

    $start_from = $current_page_start_index;

    if ($action === "back") {
        $start_from = max($start_from - 25, 0);
    } elseif ($action === "next") {
        $start_from += 25;
    }
    $anime = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM animelar WHERE id = " . (int)$anime_id));
    $anime_name = $anime['nom'];

    $episodes = mysqli_query($connect, "SELECT * FROM anime_datas WHERE id = " . (int)$anime_id . " LIMIT $start_from, 25");
    $episode_data = mysqli_fetch_all($episodes, MYSQLI_ASSOC);

    if (empty($episode_data)) {
        accl($qid, "💔 Qismlar topilmadi.", true);
        exit;
    }

    $buttons = [];
    foreach ($episode_data as $ep) {
        $ep_number = $ep['qism'];
        if ($ep_number == $current_episode) {
            $buttons[] = ['text' => "📀 $ep_number", 'callback_data' => "null"];
        } else {
            $buttons[] = ['text' => "$ep_number", 'callback_data' => "yuklanolish=$anime_id=$ep_number"];
        }
    }

    $keyboard = array_chunk($buttons, 4);

    // Navigatsiya tugmalari
    $navigation_row = [];
    if ($start_from > 0) $navigation_row[] = ['text' => "⬅️ Oldingi", 'callback_data' => "pagenation=$anime_id=" . urlencode($current_episode) . "=back"];
    $navigation_row[] = ['text' => "❌ Yopish", 'callback_data' => "close"];
    $total_episodes_q = mysqli_query($connect, "SELECT COUNT(*) as total FROM anime_datas WHERE id=$anime_id");
    $total_episodes = mysqli_fetch_assoc($total_episodes_q)['total'];
    if (($start_from + 25) < $total_episodes) $navigation_row[] = ['text' => "➡️ Keyingi", 'callback_data' => "pagenation=$anime_id=" . urlencode($current_episode) . "=next"];
    $keyboard[] = $navigation_row;

    $reply_markup = json_encode(['inline_keyboard' => $keyboard]);
    $first_ep = $episode_data[0];
    $caption = "<b>$anime_name</b>\n\n{$first_ep['qism']}-qism";

    bot('deleteMessage', [
        'chat_id' => $cid2,
        'message_id' => $message_id
    ]);

    // Fayl turiga qarab yuborish
    if ($first_ep['type'] == 'document') {
        bot('sendDocument', [
            'chat_id' => $cid2,
            'document' => $first_ep['file_id'],
            'caption' => $caption,
            'parse_mode' => "html",
            'reply_markup' => $reply_markup
        ]);
    } else { // 'video' (default)
        bot('sendVideo', [
            'chat_id' => $cid2,
            'video' => $first_ep['file_id'],
            'caption' => $caption,
            'protect_content' => $content,
            'parse_mode' => "html",
            'reply_markup' => $reply_markup
        ]);
    }
}





if($data=="allAnimes"){
     if(joinchat($cid2) == false) exit();
$result = mysqli_query($connect,"SELECT id, nom, janri FROM animelar");
$count = mysqli_num_rows($result);

$text = "$bot anime botida mavjud bo'lgan barcha animelar ro'yxati 
Barcha animelar soni : $count ta\n\n";
$counter = 1;
while($row = mysqli_fetch_assoc($result)){
$text .= "---- | $counter | ----
Anime kodi : $row[id]
Nomi : $row[nom]
Janri : $row[janri]\n\n";
$counter++;
}
put("step/animes_list_$cid2.txt",$text);
del();
bot('sendDocument',[
'chat_id'=>$cid2,
'document'=>new CURLFile("step/animes_list_$cid2.txt"),
'caption'=>"<b>📝{$bot} Anime botida mavjud bo'lgan $count ta animening ro'yxati</b>",
'parse_mode'=>"html"
]);
unlink("step/animes_list_$cid2.txt");
}

if($data=="searchByCode"){
    if(joinchat($cid2) == false) exit();
del();
sms($cid2,"<b>📌 Anime kodini kiriting:</b>",$back);
put("step/$cid2.step",$data);
}


if($step=="searchByCode"){
    if (is_numeric($text)) {
        $safe_text = (int)$text;
        $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT id FROM animelar WHERE id = $safe_text"));
        if($rew){
            unlink("step/$cid.step");
            show_anime($cid, $safe_text);
            exit();
        } else {
            sms($cid,"<b>[ $text ] kodiga tegishli anime topilmadi😔</b>\n\n• Boshqa Kod yuboring",null);
            exit();
        }
    } else {
        sms($cid,"<b>Noto'g'ri format. Faqat raqamli kod kiriting.</b>",null);
        exit();
    }
}

if ($data == "searchByGenre") {
    if(joinchat($cid2) == false) exit();
	if ($status == "VIP") {
		del();
		sms($cid2, "<b>🔍 Qidirish uchun anime janrini yuboring.</b>
📌Namuna: Syonen", $back);
		put("step/$cid2.step", $data);
	} else {
		bot('answerCallbackQuery', [
			'callback_query_id' => $qid,
			'text' => "Ushbu funksiyadan foydalanish uchun $key2 sotib olishingiz zarur!",
			'show_alert' => true,
		]);
	}
}

if ($step == "searchByGenre" and isset($text)) {
    unlink("step/$cid.step");
    show_search_results($cid, $text, 1, null, 'genre');
    exit();
}

// <---- @obito_us ---->

if(($text == $key2 or $text == "/start anipass")){
    if(joinchat($cid) == false) exit();
if($status == "Oddiy"){
sms($cid,"<b>$key2'ga ulanish

{$key2}da qanday imkoniyatlar bor?
• VIP kanal uchun 1martalik havola beriladi
• Hech qanday reklamalarsiz botdan foydalanasiz
• Majburiy obunalik soʻralmaydi</b>

$key2 haqida batafsil Qo'llanma boʻlimidan olishiz mumkin!",json_encode([
'inline_keyboard'=>[
[['text'=>"30 kun - $narx $valyuta",'callback_data'=>"shop=30"]],
[['text'=>"60 kun - ".($narx*2)." $valyuta",'callback_data'=>"shop=60"]],
[['text'=>"90 kun - ".($narx*3)." $valyuta",'callback_data'=>"shop=90"]],
]]));
exit();
}else{
$aktiv_kun = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM `status` WHERE user_id = " . (int)$cid))['kun'];
$expire=date('d.m.Y',strtotime("+$aktiv_kun days"));
sms($cid,"<b>Siz $key2 sotib olgansiz!</b>

⏳ Amal qilish muddati $expire gacha",json_encode([
'inline_keyboard'=>[
[['text'=>"🗓️ Uzaytirish",'callback_data'=>"uzaytirish"]],
]]));
exit();
}
}

if($data=="uzaytirish"){
    if(joinchat($cid2) == false) exit();
edit($cid2,$mid2,"<b>❗ Obunani necha kunga uzaytirmoqchisiz?</b>",json_encode([
'inline_keyboard'=>[
[['text'=>"30 kun - $narx $valyuta",'callback_data'=>"shop=30"]],
[['text'=>"60 kun - ".($narx*2)." $valyuta",'callback_data'=>"shop=60"]],
[['text'=>"90 kun - ".($narx*3)." $valyuta",'callback_data'=>"shop=90"]],
]]));
exit();
}

if(mb_stripos($data,"shop=")!==false){
    if(joinchat($cid2) == false) exit();
$kun = explode("=",$data)[1];
$narx /= 30;
$narx *= (int)$kun;
if($pul >= $narx){	
if($status == "Oddiy"){
$date = date('d');
mysqli_query($connect,"INSERT INTO `status` (`user_id`,`kun`,`date`) VALUES ('" . (int)$cid2 . "', '$kun', '$date')");
mysqli_query($connect,"UPDATE `user_id` SET `status` = 'VIP' WHERE user_id = " . (int)$cid2);
$a = $pul - $narx;
mysqli_query($connect,"UPDATE kabinet SET pul = $a WHERE user_id = " . (int)$cid2);
edit($cid2,$mid2,"<b>💎 VIP - statusga muvaffaqiyatli o'tdingiz.</b>",null);
adminsAlert("<a href='tg://user?id=$cid2'>Foydalanuvchi</a> $kun kunlik obuna sotib oldi!");
}else{
$aktiv_kun = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM `status` WHERE user_id = " . (int)$cid2))['kun'];
$kun = $aktiv_kun + $kun;
mysqli_query($connect,"UPDATE `status` SET kun = '$kun' WHERE user_id = " . (int)$cid2);
$a = $pul - $narx;
mysqli_query($connect,"UPDATE kabinet SET pul = $a WHERE user_id = " . (int)$cid2);
edit($cid2,$mid2,"<b>💎 VIP - statusni muvaffaqiyatli uzaytirdingiz.</b>",null);
}	
}else{
bot('answerCallbackQuery',[
'callback_query_id'=>$qid,
'text'=>"Hisobingizda yetarli mablag' mavjud emas!",
'show_alert'=>true,
]);
}
}

if($text == $key3){
    if(joinchat($cid) == false) exit();
sms($cid,"#ID: <code>$cid</code>
Balans: $pul $valyuta",null);
exit();
}


if($_GET['update']=="vip"){
// Bu cron job bo'lgani uchun joinchat tekshiruvi kerak emas.
$res = mysqli_query($connect, "SELECT * FROM `status`");
while($a = mysqli_fetch_assoc($res)){
$id = $a['user_id'];
$kun = $a['kun'];
$date = $a['date'];
if($date != date('d')){
$day = $kun - 1;
$bugun = date('d');
if($day == "0"){
mysqli_query($connect, "DELETE FROM `status` WHERE user_id = " . (int)$id);
mysqli_query($connect,"UPDATE `user_id` SET `status` = 'Oddiy' WHERE user_id = " . (int)$id);
}else{
mysqli_query($connect, "UPDATE `status` SET kun='$day',`date`='$bugun' WHERE user_id = " . (int)$id);
}
}
}
echo json_encode(['status'=>true,'cron'=>"VIP users"]);
}

//<---- @obito_us ---->//

if($text == "➕ Pul kiritish"){
    if(joinchat($cid) == false) exit();
if($turi == null){
sms($cid,"😔 To'lov tizimlari topilmadi!",null);
exit();
}else{
$turi = file_get_contents("tizim/turi.txt");
$more = explode("\n",$turi);
$soni = substr_count($turi,"\n");
$keys=[];
for ($for = 1; $for <= $soni; $for++) {
$title=str_replace("\n","",$more[$for]);
$keys[]=["text"=>"$title","callback_data"=>"pay-$title"];
}
$keysboard2 = array_chunk($keys,2);
$payment = json_encode([
'inline_keyboard'=>$keysboard2,
]);
sms($cid,"<b>💳 To'lov tizimlarni birini tanlang:</b>",$payment);
exit();
}
}

if($data == "orqa"){
    if(joinchat($cid2) == false) exit();
$turi = file_get_contents("tizim/turi.txt");
$more = explode("\n",$turi);
$soni = substr_count($turi,"\n");
$keys=[];
for ($for = 1; $for <= $soni; $for++) {
$title=str_replace("\n","",$more[$for]);
$keys[]=["text"=>"$title","callback_data"=>"pay-$title"];
$keysboard2 = array_chunk($keys,2);
$payment = json_encode([
'inline_keyboard'=>$keysboard2,
]);
}
edit($cid2,$mid2,"Quidagilardan birini tanlang:",$payment);
}

if(mb_stripos($data, "pay-")!==false){
    if(joinchat($cid2) == false) exit();
$ex = explode("-",$data);
$turi = $ex[1];
$addition = file_get_contents("tizim/$turi/addition.txt");
$wallet = file_get_contents("tizim/$turi/wallet.txt");
edit($cid2,$mid2,"<b>💳 To'lov tizimi:</b> $turi

	<b>Hamyon:</b> <code>$wallet</code>
	<b>Izoh:</b> <code>$cid2</code>

$addition",json_encode([
'inline_keyboard'=>[
	[['text'=>"☎️ Administator",'url'=>"tg://user?id=$taki_animora"]],
[['text'=>"◀️ Orqaga",'callback_data'=>"orqa"]],
]]));
}

if($text == $key5){
    if(joinchat($cid) == false) exit();
if($qollanma == null){
sms($cid,"<b>🙁 Qo'llanma qo'shilmagan!</b>",null);
exit();
}else{
sms($cid,$qollanma,null);
exit();
}
}

if($text == $key6){
    if(joinchat($cid) == false) exit();
if($homiy == null){
sms($cid,"<b>🙁 Homiylik qo'shilmagan!</b>",null);
exit();
}else{
sms($cid,$homiy,json_encode([
'inline_keyboard'=>[
[['text'=>"☎️ Administrator",'url'=>"tg://user?id=$taki_animora"]]
]]));
exit();
}
}

//<----- Admin Panel ------>

if($text == "🗄 Boshqarish" || $text == "/panel"){
if(in_array($cid,$admin)){
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Admin paneliga xush kelibsiz!</b>",
'parse_mode'=>'html',
'reply_markup'=>$panel,
]);
unlink("step/$cid.step");
unlink("step/test.txt");
unlink("step/$cid.txt");
exit();
}
}

if($data == "boshqarish"){
	bot('deleteMessage',[
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);
	bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>Admin paneliga xush kelibsiz!</b>",
	'parse_mode'=>'html',
	'reply_markup'=>$panel,
	]);
	exit();
}


$file_path = "admin/adschannel.txt";
$content = file_get_contents($file_path);

$updated_content = str_replace("@", "https://t.me/", $content);

file_put_contents($file_path, $updated_content);

$file_content = file_get_contents($file_path);

preg_match_all('/https:\/\/t\.me\/\S+/', $file_content, $matches);

if (count($matches[0]) > 0) {
    $channel_url = end($matches[0]); 
    file_put_contents($file_path, $channel_url); 
}


$kanallar = file("admin/anime_kanal.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

function createInlineButtons($id, $type = 'anime') {
    global $bot;
    $button_text = ($type == 'manga') ? "🔹 O'qish 🔹" : "🔹 Tomosha qilish 🔹";
    $start_param = ($type == 'manga') ? "manhwa_chapters_$id" : "check_$id";
    return json_encode(['inline_keyboard' => [
        [['text' => $button_text, 'url' => "https://t.me/$bot?start=$start_param"]]
    ]]);
}

function createPostText($rew) {
    global $studio_name;
    // Ma'lumotlar mavjudligini tekshirish
    $type = $rew['type'] ?? 'anime';
    $nom = $rew['nom'] ?? 'Nomsiz';
    $qismi = $rew['qismi'] ?? 'Noma\'lum';
    $janri = $rew['janri'] ?? 'Noma\'lum';
    $tili = $rew['tili'] ?? 'Noma\'lum';
    $id = $rew['id'] ?? '0';

    // Holatni aniqlash (agar `qismi` maydoni `150/150` kabi bo'lsa, tugallangan hisoblanadi)
    $status_text = "▶️ Davom etmoqda";
    if (preg_match('/(\d+)\/(\d+)/', $qismi, $matches) && $matches[1] == $matches[2] && $matches[2] != '??') {
        $status_text = "✅ Tugallangan";
    }

    if ($type == 'manga') {
        // Manga uchun post shabloni
        return "<b>$studio_name</b>\n\n"
            . "<b>📁 Manga nomi:</b> $nom\n"
            . "<b>📋 Qismlar soni:</b> $qismi\n"
            . "<b>🎭 Janri:</b> $janri\n"
            . "<b>🌐 Tili:</b> $tili\n"
            . "<b>📊 Holati:</b> $status_text\n"
            . "<b>🆔 Kodi:</b> <code>$id</code>\n";
    } else {
        // Anime uchun post shabloni
        $aniType = $rew['aniType'] ?? 'Noma\'lum';
        return "<b>$studio_name</b>\n\n"
            . "<b>🎤 Ovoz berdi:</b> $aniType\n"
            . "<b>📁 Anime nomi:</b> $nom\n"
            . "<b>📋 Qismlar soni:</b> $qismi\n"
            . "<b>🎭 Janri:</b> $janri\n"
            . "<b>🌐 Tili:</b> $tili\n"
            . "<b>📊 Holati:</b> $status_text\n"
            . "<b>🆔 Kodi:</b> <code>$id</code>\n";
    }
}

function createManhwaPostText($rew) {
    global $studio_name;
    $title = $rew['title'] ?? 'Nomsiz';
    $author = $rew['author'] ?? 'Noma\'lum';
    $genre = $rew['genre'] ?? 'Noma\'lum';
    $status = ($rew['status'] == 'completed') ? "✅ Tugallangan" : "▶️ Davom etmoqda";
    $chapters_text = $rew['chapters_text'] ?? '0/??';

    return "<b>$studio_name</b>\n\n"
        . "<b>📘 Manhwa nomi:</b> $title\n"
        . "<b>📖 Qismlar soni:</b> $chapters_text\n"
        . "<b>✍️ Muallif:</b> $author\n"
        . "<b>🎭 Janri:</b> $genre\n"
        . "<b>📊 Holati:</b> $status\n";
}

function sendAnimePost($chat_id, $rew, $web_url) {
    global $cid, $cid2; // Admin ID sini olish uchun
    global $bot, $content;
    $type = (isset($rew['rams']) && strtoupper($rew['rams'][0]) === 'B') ? 'sendVideo' : 'sendPhoto';
    $key = ($type === 'sendVideo') ? 'video' : 'photo';

    $response = bot($type, [
        'chat_id' => $chat_id,
        $key => $rew['rams'],
        'caption' => createPostText($rew),
        'parse_mode' => 'html',
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "🔹 Tomosha qilish 🔹", 'url' => "https://t.me/$bot?start=check_{$rew['id']}"]]]]),
        'protect_content'=>$content,
    ]);
    
    // Xatolikni tekshirish
    if (isset($response) && !$response->ok) {
        // Agar post yuborishda xatolik bo'lsa, adminga xabar berish
        $error_message = "<b>⚠️ Post yuborishda xatolik!</b>\n\n";
        $error_message .= "<b>Kanal:</b> <code>$chat_id</code>\n";
        $error_message .= "<b>Xato:</b> " . ($response->description ?? 'Noma\'lum xato');
        sms($cid2, $error_message, null);
    }
    return $response;
}

function sendManhwaPost($chat_id, $rew) {
    global $cid, $cid2, $bot;

    $response = bot('sendPhoto', [
        'chat_id' => $chat_id,
        'photo' => $rew['cover_file_id'],
        'caption' => createManhwaPostText($rew),
        'parse_mode' => 'html',
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => "🔹 O'qish 🔹", 'url' => "https://t.me/$bot?start=manhwa_chapters_{$rew['id']}"]]
        ]]),
    ]);

    if (isset($response) && !$response->ok) {
        $error_message = "<b>⚠️ Manhwa postini yuborishda xatolik!</b>\n\n";
        $error_message .= "<b>Kanal:</b> <code>$chat_id</code>\n";
        $error_message .= "<b>Xato:</b> " . ($response->description ?? 'Noma\'lum xato');
        sms($cid2, $error_message, null);
    }
    return $response;
}


function kanal_tugmalari($id) {
    global $kanallar;
    $buttons = [];

    foreach ($kanallar as $kanal) {
        $buttons[] = [['text' => "📤 $kanal ga yuborish", 'callback_data' => "sendto=$kanal|$id"]];
    }

    $buttons[] = [['text' => "📡 BARCHA kanallarga yuborish", 'callback_data' => "sendto=ALL|$id"]];
    return json_encode(['inline_keyboard' => $buttons]);
}


if ($text == "📬 Post tayyorlash" and in_array($cid, $admin)) {
    sms($cid, "<b>📬 Qaysi turdagi kontentni kanalga yubormoqchisiz?</b>", json_encode([
        'inline_keyboard' => [
            [['text' => "🎬 Anime", 'callback_data' => "create_post_type=anime"]],
            [['text' => "📚 Manhwa", 'callback_data' => "create_post_type=manhwa"]],
        ]
    ]));
    exit();
}

if (strpos($data, "create_post_type=") === 0) {
    $type = str_replace("create_post_type=", "", $data);
    sms($cid2, "<b>🆔 Kodni kiriting:</b>", $boshqarish);
    put("step/$cid2.step", "createPost_$type");
}

if ($step == "createPost" and in_array($cid, $admin)) {
    sms($cid, "📬 Qaysi kanalga yuborilsin?", kanal_tugmalari($text));
    exit();
}

if (strpos($data, "sendto=") !== false) {
    list($kanal, $id) = explode("|", explode("=", $data)[1]);
    $safe_id = (int)$id;
    $post_type = get("step/post_type_{$cid2}.txt"); // anime yoki manhwa

    if ($post_type == 'anime') {
        $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM animelar WHERE id = $safe_id"));
        if (!$rew) {
            sms($cid2, "❌ ID raqami <b>$safe_id</b> bo'lgan anime topilmadi!", null);
            exit();
        }

        if ($kanal == "ALL") {
            foreach ($kanallar as $kanal_name) sendAnimePost($kanal_name, $rew, null);
            sms($cid2, "✅ Anime postingiz barcha kanallarga yuborildi!", $panel);
        } else {
            sendAnimePost($kanal, $rew, null);
            sms($cid2, "✅ Anime postingiz <b>$kanal</b> ga yuborildi!", $panel);
        }
        sendAnimePost($cid2, $rew, null); // Adminga namuna

    } elseif ($post_type == 'manhwa') {
        $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM manhwas WHERE id = $safe_id"));
        if (!$rew) {
            sms($cid2, "❌ ID raqami <b>$safe_id</b> bo'lgan manhwa topilmadi!", null);
            exit();
        }

        if ($kanal == "ALL") {
            foreach ($kanallar as $kanal_name) sendManhwaPost($kanal_name, $rew);
            sms($cid2, "✅ Manhwa postingiz barcha kanallarga yuborildi!", $panel);
        } else {
            sendManhwaPost($kanal, $rew);
            sms($cid2, "✅ Manhwa postingiz <b>$kanal</b> ga yuborildi!", $panel);
        }
        sendManhwaPost($cid2, $rew); // Adminga namuna
    }

    unlink("step/post_type_{$cid2}.txt");
    exit();
}

if (strpos($step, "createPost_") === 0 and in_array($cid, $admin)) {
    $type = str_replace("createPost_", "", $step);
    put("step/post_type_{$cid}.txt", $type);
    sms($cid, "📬 Qaysi kanalga yuborilsin?", kanal_tugmalari($text));
    unlink("step/$cid.step");
    exit();
}


if($text == "🔎 Foydalanuvchini boshqarish"){
if(in_array($cid,$admin)){
	bot('SendMessage',[

	'chat_id'=>$cid,
	'text'=>"<b>Kerakli foydalanuvchining ID raqamini kiriting:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>$boshqarish
	]);
file_put_contents("step/$cid.step",'iD');
exit();
}
}

if($step == "iD"){
if(in_array($cid,$admin)){
$safe_text = (int)$text;
$result = mysqli_query($connect,"SELECT * FROM user_id WHERE user_id = $safe_text");
$row = mysqli_fetch_assoc($result);
if(!$row){
bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Foydalanuvchi topilmadi.</b>

Qayta urinib ko'ring:",
'parse_mode'=>'html',
]);
exit();
}else{
$pul = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_text"))['pul'];
$odam = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_text"))['odam'];
$ban = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_text"))['ban'];
$status = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM status WHERE user_id = $safe_text"))['status'];
if($status == "Oddiy"){
	$vip = "💎 VIP ga qo'shish";
}else{
	$vip = "❌ VIP dan olish";
}
if($ban == "unban"){
	$bans = "🔔 Banlash";
}else{
	$bans = "🔕 Bandan olish";
}
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Qidirilmoqda...</b>",
'parse_mode'=>'html',
]);
bot('editMessageText',[
        'chat_id'=>$cid,
        'message_id'=>$mid + 1,
        'text'=>"<b>Qidirilmoqda...</b>",
       'parse_mode'=>'html',
]);
bot('editMessageText',[
      'chat_id'=>$cid,
     'message_id'=>$mid + 1,
'text'=>"<b>Foydalanuvchi topildi!

ID:</b> <a href='tg://user?id=$safe_text'>$safe_text</a>
<b>Balans: $pul $valyuta
Takliflar: $odam ta</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
	'inline_keyboard'=>[
[['text'=>"$bans",'callback_data'=>"ban-$safe_text"]],
[['text'=>"$vip",'callback_data'=>"addvip-$safe_text"]],
[['text'=>"➕ Pul qo'shish",'callback_data'=>"plus-$safe_text"],['text'=>"➖ Pul ayirish",'callback_data'=>"minus-$safe_text"]]
]
])
]);
unlink("step/$cid.step");
exit();
}
}
}

if(mb_stripos($data, "foyda-")!==false){
$id = explode("-", $data)[1];
$safe_id = (int)$id;
$pul = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['pul'];
$odam = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['odam'];
$ban = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['ban'];
$status = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM status WHERE user_id = $safe_id"))['status'];
if($status == "Oddiy"){
	$vip = "💎 VIP ga qo'shish";
}else{
	$vip = "❌ VIP dan olish";
}
if($ban == "unban"){
	$bans = "🔔 Banlash";
}else{
	$bans = "🔕 Bandan olish";
}
bot('deleteMessage',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
]);
bot('SendMessage',[
'chat_id'=>$cid2,
'text'=>"<b>Foydalanuvchi topildi!

ID:</b> <a href='tg://user?id=$safe_id'>$safe_id</a>
<b>Balans: $pul $valyuta
Takliflar: $odam ta</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
	'inline_keyboard'=>[
[['text'=>"$bans",'callback_data'=>"ban-$safe_id"]],
[['text'=>"$vip",'callback_data'=>"addvip-$safe_id"]],
[['text'=>"➕ Pul qo'shish",'callback_data'=>"plus-$safe_id"],['text'=>"➖ Pul ayirish",'callback_data'=>"minus-$safe_id"]]
]
])
]);
exit();
}

//<---- @obito_us ---->//

if(mb_stripos($data, "plus-")!==false){
$id = explode("-", $data)[1];
$safe_id = (int)$id;
bot('editMessageText',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
'text'=>"<a href='tg://user?id=$safe_id'>$safe_id</a> <b>ning hisobiga qancha pul qo'shmoqchisiz?</b>",
'parse_mode'=>"html",
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
	[['text'=>"◀️ Orqaga",'callback_data'=>"foyda-$safe_id"]]
]
])
]);
file_put_contents("step/$cid2.step","plus-$id");
}

if(mb_stripos($step, "plus-")!==false){
$id = explode("-", $step)[1];
$safe_id = (int)$id;
if(in_array($cid,$admin)){
if(is_numeric($text)=="true"){
bot('sendMessage',[
'chat_id'=>$safe_id,
'text'=>"<b>Adminlar tomonidan hisobingiz $text $valyuta to'ldirildi!</b>",
'parse_mode'=>"html",
]);
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Foydalanuvchi hisobiga $text $valyuta qo'shildi!</b>",
'parse_mode'=>"html",
'reply_markup'=>$panel,
]);
$pul = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['pul'];
$pul2 = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['pul2'];
$a = $pul + $text;
$b = $pul2 + $text;
mysqli_query($connect,"UPDATE kabinet SET pul = $a WHERE user_id = $safe_id");
mysqli_query($connect,"UPDATE kabinet SET pul2 = $b WHERE user_id = $safe_id");
if($cash == "Yoqilgan"){
$refid = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM user_id WHERE user_id = $safe_id"))['refid'];
$pul3 = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = " . (int)$refid))['pul'];
$c = $cashback / 100 * $text;
$jami = $pul3 + $c;
mysqli_query($connect,"UPDATE kabinet SET pul = $jami WHERE user_id = $refid");
}
bot('SendMessage',[
	'chat_id'=>$refid,
    'text'=>"💵 <b>Do'stingiz hisobini to'ldirganligi uchun sizga $cashback% cashback berildi!</b>",
	'parse_mode'=>'html',
]);
unlink("step/$cid.step");
exit();
}else{
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Faqat raqamlardan foydalaning!</b>",
'parse_mode'=>'html',
]);
exit();
}
}
}

if(mb_stripos($data, "minus-")!==false){
$id = explode("-", $data)[1];
$safe_id = (int)$id;
bot('editMessageText',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
'text'=>"<a href='tg://user?id=$safe_id'>$safe_id</a> <b>ning hisobiga qancha pul ayirmoqchisiz?</b>",
'parse_mode'=>"html",
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
	[['text'=>"◀️ Orqaga",'callback_data'=>"foyda-$safe_id"]]
]
])
]);
file_put_contents("step/$cid2.step","minus-$id");
}

if(mb_stripos($step, "minus-")!==false){
$id = explode("-", $step)[1];
$safe_id = (int)$id;
if(in_array($cid,$admin)){
if(is_numeric($text)=="true"){
bot('sendMessage',[
'chat_id'=>$safe_id,
'text'=>"<b>Adminlar tomonidan hisobingizdan $text $valyuta olib tashlandi!</b>",
'parse_mode'=>"html",
]);
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Foydalanuvchi hisobidan $text $valyuta olib tashlandi!</b>",
'parse_mode'=>"html",
'reply_markup'=>$panel,
]);
$pul = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['pul'];
$a = $pul - $text;
mysqli_query($connect,"UPDATE kabinet SET pul = $a WHERE user_id = $safe_id");
unlink("step/$cid.step");
exit();
}else{
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Faqat raqamlardan foydalaning!</b>",
'parse_mode'=>'html',
]);
exit();
}
}
}

if(mb_stripos($data, "ban-")!==false){
$id = explode("-", $data)[1];
$safe_id = (int)$id;
$ban = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM kabinet WHERE user_id = $safe_id"))['ban'];
if($taki_animora != $id){
	if($ban == "ban"){
		$text = "<b>Foydalanuvchi ($safe_id) bandan olindi!</b>";
		mysqli_query($connect,"UPDATE kabinet SET ban = 'unban' WHERE user_id = $safe_id");
}else{
	$text = "<b>Foydalanuvchi ($safe_id) banlandi!</b>";
	mysqli_query($connect,"UPDATE kabinet SET ban = 'ban' WHERE user_id = $safe_id");
}
bot('editMessageText',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
'text'=>$text,
'parse_mode'=>"html",
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
	[['text'=>"◀️ Orqaga",'callback_data'=>"foyda-$safe_id"]]
]
])
]);
}else{
bot('answerCallbackQuery',[
'callback_query_id'=>$qid,
'text'=>"Asosiy adminlarni blocklash mumkin emas!",
'show_alert'=>true,
]);
}
}

if(mb_stripos($data, "addvip-")!==false){
$id = explode("-", $data)[1];
$safe_id = (int)$id;
$status = mysqli_fetch_assoc(mysqli_query($connect,"SELECT * FROM status WHERE user_id = $safe_id"))['status'];
if($status == "VIP"){
	$text = "<b>Foydalanuvchi ($safe_id) VIP dan olindi!</b>";
	mysqli_query($connect,"UPDATE status SET kun = '0' WHERE user_id = $safe_id");
	mysqli_query($connect,"UPDATE status SET status = 'Oddiy' WHERE user_id = $safe_id");
}else{
	$text = "<b>Foydalanuvchi ($safe_id) VIP ga qo'shildi!</b>";
	mysqli_query($connect,"UPDATE status SET kun = '30' WHERE user_id = $safe_id");
	mysqli_query($connect,"UPDATE status SET status = 'VIP' WHERE user_id = $safe_id");
}
bot('editMessageText',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
'text'=>$text,
'parse_mode'=>"html",
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
	[['text'=>"◀️ Orqaga",'callback_data'=>"foyda-$safe_id"]]
]
])
]);
}

if($text == "✉ Xabar Yuborish" and in_array($cid,$admin)){
$result = mysqli_query($connect, "SELECT * FROM send");
$row = mysqli_fetch_assoc($result);
if(!$row){
bot('sendMessage',[
'chat_id'=>$cid,
'text'=>"<b>❓ Haqiqatdan ham barcha foydalanuvchilarga xabar yubormoqchimisiz?</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[
    ['text'=>"✅ Ha",'callback_data'=>"start_send"],
    ['text'=>"❌ Yo'q",'callback_data'=>"cancel_send_initial"]
]
]
])
]);
exit;
}else{
sms($cid, "<b>📑 Hozirda botda xabar yuborish jarayoni davom etmoqda.</b>\n\n<i>Jarayonni bekor qilish uchun quyidagi tugmani bosing:</i>", json_encode([
'inline_keyboard'=>[
    [['text'=>"❌ Jarayonni bekor qilish",'callback_data'=>"cancel_send"]],
]
]));
exit;
}
}

if($data == "cancel_send_initial" and in_array($cid2, $admin)){
    edit($cid2, $mid2, "<b>Admin paneliga xush kelibsiz!</b>", $panel);
    exit();
}

if($data == "start_send" and in_array($cid2, $admin)){
    mysqli_query($connect, "TRUNCATE TABLE send");
    edit($cid2, $mid2, "<b>📤 Foydalanuvchilarga yuboriladigan xabarni botga yuboring!</b>", $boshqarish);
    put("step/$cid2.step", "sends"); 
    exit();
}

if($data == "cancel_send" and in_array($cid2, $admin)){
    $result = mysqli_query($connect, "TRUNCATE TABLE send");
    if($result){
        edit($cid2, $mid2, "<b>✅ Xabar yuborish jarayoni muvaffaqiyatli bekor qilindi. Endi yangi xabar yuborishingiz mumkin.</b>", $asosiy);
    } else {
        accl($qid, "❌ Jarayonni bekor qilishda xatolik yuz berdi!", true);
    }
    exit();
}

if($step== "sends" and in_array($cid,$admin)){
	unlink("step/$cid.step");
    mysqli_query($connect, "INSERT INTO send (status) VALUES ('sending')");
    sms($cid, "<b>✅ Xabar yuborish boshlandi! Jarayon biroz vaqt olishi mumkin...</b>", $panel);

    $query = $connect->query("SELECT user_id FROM kabinet");
    $user_ids = [];
    while ($row = $query->fetch_assoc()) {
        $user_ids[] = $row['user_id'];
    }

    $user_chunks = array_chunk($user_ids, 50);
    $sent_count = 0;

    foreach ($user_chunks as $chunk) {
        multi_curl_forward($chunk, $cid, $mid);
        $sent_count += count($chunk);
        sleep(1);
    }

    mysqli_query($connect, "TRUNCATE TABLE send");
    sms($cid,"<b>✅ Xabar yuborish tugallandi!</b>\n\nJami <b>$sent_count</b> ta foydalanuvchiga yuborildi.",null);
    exit();
}

// <---- @taki_animora ---->

function show_stats_menu($chat_id, $message_id = null) {
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => "📈 Umumiy statistika", 'callback_data' => "stats_general"]],
            [['text' => "📅 Yangi a'zolar", 'callback_data' => "stats_new_users"]],
            [['text' => "🌟 Faol animelar", 'callback_data' => "stats_top_animes"]],
            [['text' => "◀️ Orqaga", 'callback_data' => "boshqarish"]]
        ]
    ]);
    $text = "<b>📊 Kengaytirilgan statistika paneli</b>\n\nKerakli bo'limni tanlang:";

    if ($message_id) {
        edit($chat_id, $message_id, $text, $keyboard);
    } else {
        sms($chat_id, $text, $keyboard);
    }
}

if ($text == "📊 Statistika" and in_array($cid, $admin)) {
    show_stats_menu($cid);
    exit();
}

if ($data == "stats_menu") {
    show_stats_menu($cid2, $mid2);
    exit();
}

if ($data == "stats_general") {
    $total_users = mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `kabinet`"));
    $vip_users = mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `user_id` WHERE `status`='VIP'"));
    $total_animes = mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `animelar`"));
    $total_episodes = mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `anime_datas`"));

    $text = "<b>📈 Umumiy statistika:</b>\n\n" .
            "👥 Jami foydalanuvchilar: <b>$total_users</b> ta\n" .
            "💎 VIP a'zolar: <b>$vip_users</b> ta\n" .
            "🎬 Jami animelar: <b>$total_animes</b> ta\n" .
            "🎞 Jami qismlar: <b>$total_episodes</b> ta";

    edit($cid2, $mid2, $text, json_encode(['inline_keyboard' => [[['text' => "◀️ Orqaga", 'callback_data' => "stats_menu"]]]]));
    exit();
}

if ($data == "stats_new_users") {
    $today_date = date("d.m.Y");
    $week_start = date("d.m.Y", strtotime('monday this week'));
    $month_start = date("01.m.Y");

    $today_users = mysqli_num_rows(mysqli_query($connect, "SELECT `id` FROM `user_id` WHERE `sana` = '$today_date'"));
    
    // Haftalik va oylik uchun SQL da sanani formatlash
    $weekly_users_query = mysqli_query($connect, "SELECT COUNT(id) as count FROM user_id WHERE STR_TO_DATE(sana, '%d.%m.%Y') >= STR_TO_DATE('$week_start', '%d.%m.%Y')");
    $weekly_users = mysqli_fetch_assoc($weekly_users_query)['count'];

    $monthly_users_query = mysqli_query($connect, "SELECT COUNT(id) as count FROM user_id WHERE STR_TO_DATE(sana, '%d.%m.%Y') >= STR_TO_DATE('$month_start', '%d.%m.%Y')");
    $monthly_users = mysqli_fetch_assoc($monthly_users_query)['count'];

    $text = "<b>📅 Yangi a'zolar statistikasi:</b>\n\n" .
            "☀️ Bugun: <b>+$today_users</b> ta\n" .
            "🗓 Shu hafta: <b>+$weekly_users</b> ta\n" .
            "🌙 Shu oy: <b>+$monthly_users</b> ta";

    edit($cid2, $mid2, $text, json_encode(['inline_keyboard' => [[['text' => "◀️ Orqaga", 'callback_data' => "stats_menu"]]]]));
    exit();
}

if ($data == "stats_top_animes") {
    $top_viewed_query = mysqli_query($connect, "SELECT nom, qidiruv FROM animelar ORDER BY qidiruv DESC LIMIT 1");
    $top_viewed = mysqli_fetch_assoc($top_viewed_query);
    $top_viewed_text = $top_viewed ? "{$top_viewed['nom']} (👁️ {$top_viewed['qidiruv']})" : "Noma'lum";

    $top_saved_query = mysqli_query($connect, "SELECT a.nom, COUNT(w.anime_id) AS save_count FROM user_watchlist w JOIN animelar a ON w.anime_id = a.id GROUP BY w.anime_id ORDER BY save_count DESC LIMIT 1");
    $top_saved = mysqli_fetch_assoc($top_saved_query);
    $top_saved_text = $top_saved ? "{$top_saved['nom']} (❤️ {$top_saved['save_count']})" : "Noma'lum";

    $text = "<b>🌟 Faol animelar statistikasi:</b>\n\n" .
            "👁️ Eng ko'p ko'rilgan:\n<b>$top_viewed_text</b>\n\n" .
            "❤️ Eng ko'p saqlangan:\n<b>$top_saved_text</b>";

    $keyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "👁️ Top 10", 'callback_data' => "topViewers"],
                ['text' => "❤️ Top 10", 'callback_data' => "topWatchlist"]
            ],
            [
                ['text' => "◀️ Orqaga", 'callback_data' => "stats_menu"]
            ]
        ]
    ]);

    edit($cid2, $mid2, $text, $keyboard);
    exit();
}


// <---- @taki_animora ---->

if($text == "📢 Kanallar"){
	if(in_array($cid,$admin)){
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
	[['text'=>"🔐 Majburiy obuna",'callback_data'=>"majburiy"]],
	[['text'=>"📌 Qo'shimcha kanalar",'callback_data'=>"qoshimchakanal"]],
	]
	])
	]);
	exit();
}
}

if($data == "kanallar"){
	bot('deleteMessage',[
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);
	bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
	[['text'=>"🔐 Majburiy obuna",'callback_data'=>"majburiy"]],
	[['text'=>"📌 Qo'shimcha kanalar",'callback_data'=>"qoshimchakanal"]],
]
	])
	]);
	exit();
}

/*INSTAGRAM QO'SHISH FUNKSIYASI  @ITACHI_UCHIHA_SONO_SHARINGAN TOMONIDAN ISHLAB CHIQILDI */

if($data == "qoshimchakanal"){  
     bot('editMessageText',[
        'chat_id'=>$cid2,
        'message_id'=>$mid2,
'text'=>"<b>Qo'shimcha kanallar sozlash bo'limidasiz:</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"🎥 Anime kanal",'callback_data'=>"anime-kanal"]],
[['text'=>"🎁 Ijtimoiy tarmoqlar", 'callback_data'=>"social"]],
[['text'=>"◀️ Orqaga",'callback_data'=>"kanallar"]]
]
])
]);
}

if ($data == 'social') {
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id'=>$mid2,
        'text' => "🌐 O'zingizga kerakli 🎁 ijtimoiy tarmoqni tanlang!",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "📸 Instagram", 'callback_data' => 'channel=insta']],
                [['text' => "🎥 YouTube", 'callback_data' => 'channel=youtube']],
            ],
        ]),
    ]);
}

if (strpos($data, 'channel=') === 0) {
    $channel_name = str_replace('channel=', '', $data);

    if ($channel_name == 'insta') {
        bot('editMessageText',[
            'chat_id'=>$cid2,
            'message_id'=>$mid2,
            'text'=>"📸 Instagram ustida qanday amal bajaramiz? 👇",
            'reply_markup'=>json_encode([
            'inline_keyboard'=>[
            [['text'=>"➕ Kanal qo'shish 💬",'callback_data'=>"newchann=instaplus"],['text'=>"🗑 Kanal o'chirish ❌",'callback_data'=>"delchann=instaminus"]],
            [['text'=>"📃 Ro'yhatni ko'rish 📝", 'callback_data'=>'lists=insta']],
        ],
    ]),
]);
    } elseif ($channel_name == 'youtube') {
         bot('editMessageText',[
            'chat_id'=>$cid2,
            'message_id'=>$mid2,
            'text'=>"🎥 YouTube ustida qanday amal bajaamiz? 👇",
            'reply_markup'=>json_encode([
            'inline_keyboard'=>[
            [['text'=>"➕ Kanal qo'shish 🎬",'callback_data'=>"newchann=youtubeplus"],['text'=>"🗑 Kanal o'chirish ❌",'callback_data'=>"delchann=youtube"]],
            [['text'=>"📃 Ro'yhatni ko'rish 📝", 'callback_data'=>'lists=youtube']],
        ],
    ]),
]);
    }
    exit();
}



if (strpos($data, 'newchann=') === 0) {
    $channel_name = str_replace('newchann=', '', $data);
    if($channel_name = 'instaplus'){
        sms($cid2,"📸 <b>Instagram sahifangizga havola:</b>\n\n🌐 <a href='https://www.instagram.com/'>Instagramni ochish uchun bosing!</a> ✨",null);
        put('insta.txt','kanal');
    }elseif($channel_name = 'youtubeplus'){
        sms($cid2,"📸 <b>Instagram sahifangizga havola:</b>\n\n🌐 <a href='https://www.instagram.com/'>Instagramni ochish uchun bosing!</a> ✨",null);
        put('insta.txt','ytkanal');
    }
    exit();
}

if (strpos($data, 'delchann=') === 0) {
         $channel_name = str_replace('delchann=', '', $data);
         if($channel_name == 'instaminus'){
             $channelinsta = get('admin/instagram.txt');
             if(!empty($channelinsta)){
                  edit($cid2,$mid2,"✅ Sizning Instagram profilingiz muvaffaqiyatli o‘chirildi! 🗑️📸",null);
                     unlink('admin/instagram.txt');
             } else {
                  edit($cid2,$mid2,"📸 <b>Sizning Instagram profilingiz mavjud emas!</b> ❌",null);
             } 
         } else{
             $channelinsta = get('admin/youtube.txt');
             if(!empty($channelinsta)){
                  edit($cid2,$mid2,"✅ Sizning Youtube profilingiz muvaffaqiyatli o‘chirildi! 🗑️📸",null);
                     unlink('admin/youtube.txt');
             } else {
                  edit($cid2,$mid2,"📸 <b>Sizning Youtube profilingiz mavjud emas!</b> ❌",null);
             } 
         }
    exit();
}


$insta = get('insta.txt');

if ($insta == 'kanal' && isset($text)) {
    if (strpos($text, 'https://www.instagram.com/') !== false) {
        sms($cid, "✅ Sizning Instagram profilingiz havolasi qabul qilindi:", null);
        unlink('insta.txt');
        put('admin/instagram.txt', $text);
    } elseif (strpos($text, 'https://www.youtube.com/') !== false || strpos($text, 'https://youtu.be/') !== false) {
        sms($cid, "✅ Sizning YouTube profilingiz havolasi qabul qilindi:", null);
        unlink('insta.txt');
        put('admin/youtube.txt', $text);
    } else {
        sms($cid, "❌ Iltimos, to‘g‘ri Instagram yoki YouTube havolasini yuboring!\n\n🔹 **Instagram:** <code>https://www.instagram.com/foydalanuvchi_nomi</code>\n🔹 **YouTube:** <code>https://www.youtube.com/channel/kanal_id</code>", null);
    }
    exit();
}


     if (strpos($data, 'lists=') === 0) {
         $channel_name = str_replace('lists=', '', $data);
         if($channel_name == 'insta'){
             $channelinsta = get('admin/instagram.txt');
             if(!empty($channelinsta)){
                     edit($cid2,$mid2,"🌟 <b>Sizning Instagram profillaringiz:</b> \n\n $channelinsta",null);
             } else {
                     edit($cid2,$mid2,"🌟 <b>Sizning Instagram profilingiz mavjud emas:</b>",null);
             }
         } elseif($channel_name == 'youtube'){
             $channelinsta = get('admin/youtube.txt');
             if(!empty($channelinsta)){
                     edit($cid2,$mid2,"🌟 <b>Sizning YouTube profillaringiz:</b> \n\n $channelinsta",null);
             } else {
                 edit($cid2,$mid2,"🌟 <b>Sizning YouTube profilingiz mavjud emas:</b>",null);
             }
         }
         exit();
    } 
    
if($data == "anime-kanal" or $data == "animekanal2") {
    $step_name = ($data == "anime-kanal") ? "anime-kanal1" : "animekanal2";

    bot('deleteMessage', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
    ]);

    bot('sendMessage', [
        'chat_id' => $cid2,
        'text' => "📢 <b>Anime kanal ustida qanday amal bajaramiz?</b>",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard'=>[
                [['text'=>"➕ Qo'shish",'callback_data'=>'add_anime_channel']],
                [['text'=>"📃 Ro'yhat",'callback_data'=>'list_anime_channel'], ['text'=>"🗑 O'chirish",'callback_data'=>'delete_anime_channel']],
            ]
        ]),
    ]);
    file_put_contents("step/$cid2.step", $step_name);
    exit();
}

$channel_file = "admin/anime_kanal.txt";

if($data == "add_anime_channel") {
    del();
    file_put_contents("step/$cid2.step", "add_anime_channel");
    bot('sendMessage', [
        'chat_id' => $cid2,
        'text' => "📨 <b>Kanal usernamesini yuboring</b>\nNamuna: <code>@kanal_username</code>",
        'parse_mode' => 'html'
    ]);
    exit();
}

if($step == "add_anime_channel" and isset($text)) {
    if(strpos($text, "@") === 0) {
        $all = file_get_contents($channel_file);
        if(mb_stripos($all, $text) === false){
            $text = trim($text);
            $all = trim($all);
            if($all != "") {
                file_put_contents($channel_file, "\n$text", FILE_APPEND);
            } else {
                file_put_contents($channel_file, $text, FILE_APPEND);
            }
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "✅ <b>Kanal qo‘shildi:</b> <code>$text</code>",
                'parse_mode' => 'html',
                'reply_markup' => $panel
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "❗️Bu kanal allaqachon mavjud!",
                'parse_mode' => 'html'
            ]);
        }
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❗️To'g'ri formatda yuboring. Namuna: <code>@kanalim</code>",
            'parse_mode' => 'html'
        ]);
    }
    unlink("step/$cid.step");
    exit();
}


if($data == "list_anime_channel") {
    $list = file($channel_file, FILE_IGNORE_NEW_LINES);
    if(count($list) == 0){
        $text = "📃 Ro‘yxatda kanal yo‘q.";
    } else {
        $text = "📃 <b>Anime kanallar ro‘yxati:</b>\n\n";
        $i = 1;
        foreach($list as $channel){
            $text .= "$i. <code>$channel</code>\n";
            $i++;
        }
    }

    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => $text,
        'parse_mode' => 'html'
    ]);
    exit();
}

if($data == "delete_anime_channel") {
    $list = file($channel_file, FILE_IGNORE_NEW_LINES);
    if(count($list) == 0){
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "🗑 Ro‘yxatda kanal yo‘q.",
        ]);
    } else {
        $buttons = [];
        foreach($list as $key => $val){
            $buttons[] = [['text' => $key + 1, 'callback_data' => "del_kanal_$key"]];
        }
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "🗑 <b>O‘chirmoqchi bo‘lgan kanal raqamini tanlang:</b>",
            'parse_mode' => 'html',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
    }
    exit();
}

if(mb_stripos($data, "del_kanal_") !== false){
    $del_index = str_replace("del_kanal_", "", $data);
    $list = file($channel_file, FILE_IGNORE_NEW_LINES);
    if(isset($list[$del_index])){
        $removed = $list[$del_index];
        unset($list[$del_index]);
        file_put_contents($channel_file, implode("\n", $list));
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "✅ <b>$removed</b> kanali o‘chirildi.",
            'parse_mode' => 'html'
        ]);
    }
    exit();
}
if ($data == "majburiy") {
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "<b>🔐Majburiy obunalarni sozlash bo'limidasiz:</b>",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "➕ Qo'shish", 'callback_data' => "qoshish"]],
                [['text' => "📑 Ro'yxat", 'callback_data' => "royxat"], ['text' => "🗑 O'chirish", 'callback_data' => "ochirish"]],
                [['text' => "🔙Ortga", 'callback_data' => "kanallar"]]
            ]
        ])
    ]);
}

if ($data == "cancel" && in_array($cid2, $admin)) {
    del();
    sms($cid2, "<b>✅Bekor qilindi !</b>", $panel);
}

if ($data == "qoshish") {
    del();
    sms($cid2, "<b>💬Kanal IDsini yuboring !</b>", $boshqarish);
    file_put_contents("step/$cid2.step", "addchannel=id");
    exit();
}

if (stripos($step, "addchannel=") !== false && in_array($cid, $admin)) {
    $ty = str_replace("addchannel=", '', $step);

    if ($ty == "id" && (is_numeric($text) || stripos($text, "-100") !== false)) {
        if (stripos($text, "-100") !== false) $text = str_replace("-100", '', $text);
        $text = "-100" . $text;
        file_put_contents("step/addchannel.txt", $text);
        sms($cid, "<b>🔗Kanal havolasini kiriting !</b>", null);
        file_put_contents("step/$cid.step", "addchannel=link");
        exit();
    } elseif (stripos($text, "https://") !== false) {
        if (preg_match("~https://t\.me/|https://telegram\.dog/|https://telegram\.me/~", $text)) {
            file_put_contents("step/addchannelLink.txt", $text);
            // delkey();
            sms($cid, "<b>⚠️Ushbu kanal zayafka kanal sifatida qo'shilsinmi?</b>", json_encode([
                'inline_keyboard' => [
                    [['text' => "✅Ha", 'callback_data' => "addChannel=request"], ['text' => "❌Yo‘q", 'callback_data' => "addChannel=lock"]],
                    [['text' => "🚫Bekor qilish", 'callback_data' => "cancel"]]
                ]   
            ]));
            unlink("step/$cid2.step");
            exit();
        } else {
            sms($cid, "<b>📍Faqat Telegram uchun ishlaydi!</b>", null);
            exit();
        }
    }
}

if (stripos($data, "addChannel=") !== false && in_array($cid2, $admin)) {
    $ty = str_replace("addChannel=", '', $data);
    $channelId = file_get_contents("step/addchannel.txt");
    $channelLink = file_get_contents("step/addchannelLink.txt");

    $sql = "INSERT INTO `channels`(`channelId`, `channelType`, `channelLink`) VALUES ('$channelId', '$ty', '$channelLink')";

    if ($connect->query($sql)) {
        del();
        sms($cid2, "<b>✅Majburiy obunaga kanal ulandi!</b>", $panel);
        unlink("step/addchannel.txt");
        unlink("step/addchannelLink.txt");
    } else {
        accl($qid, "⚠️Tizimda xatolik!\n\n" . $connect->error, 1);
    }
}

if ($data == "ochirish") {
    $query = $connect->query("SELECT * FROM `channels`");

    if ($query->num_rows > 0) {
        $soni = $query->num_rows;
        $text = "<b>✂️Kanalni uzish uchun kanal raqami ustiga bosing!</b>\n";
        $co = 1;
        while ($row = $query->fetch_assoc()) {
            $text .= "\n<b>$co.</b> " . $row['channelLink'] . " | " . $row['channelType'];
            $uz[] = ['text' => "🗑️$co", 'callback_data' => "channelDelete=" . $row['id']];
            $co++;
        }
        $e = array_chunk($uz, 5);
        $e[] = [['text' => "🔙Ortga", 'callback_data' => "majburiy"]];
        $json = json_encode(['inline_keyboard' => $e]);
        $text .= "\n\n<b>Ulangan kanallar soni:</b> $soni ta";
        edit($cid2, $mid2, $text, $json);
    } else {
        accl($qid, "Hech qanday kanallar ulanmagan!", 1);
    }
}

if (stripos($data, "channelDelete=") !== false && in_array($cid2, $admin)) {
    $ty = str_replace("channelDelete=", '', $data);
    $sql = "DELETE FROM `channels` WHERE `id` = '$ty'";

    if ($connect->query($sql)) {
        accl($qid, "Kanal uzildi✔️");
        $query = $connect->query("SELECT * FROM `channels`");

        if ($query->num_rows > 0) {
            $soni = $query->num_rows;
            $text = "<b>✂️Kanalni uzish uchun kanal raqami ustiga bosing!</b>\n";
            $co = 1;
            $uz = [];
            while ($row = $query->fetch_assoc()) {
                $text .= "\n<b>$co.</b> " . $row['channelLink'] . " | " . $row['channelType'];
                $uz[] = ['text' => "🗑️$co", 'callback_data' => "channelDelete=" . $row['id']];
                $co++;
            }
            $e = array_chunk($uz, 5);
            $e[] = [['text' => "🔙Ortga", 'callback_data' => "majburiy"]];
            $json = json_encode(['inline_keyboard' => $e]);
            $text .= "\n\n<b>Ulangan kanallar soni:</b> $soni ta";
            edit($cid2, $mid2, $text, $json);
        } else {
            del();
            sms($cid2, "<b>☑️Majburiy obuna ulangan kanallar qolmadi!</b>", $panel);
        }
    } else {
        accl($qid, "⚠️Tizimda xatolik!\n\n" . $connect->error, 1);
    }
}

if ($data == "royxat") {
    $query = $connect->query("SELECT * FROM `channels`");

    if ($query->num_rows > 0) {
        $soni = $query->num_rows;
        $text = "<b>📢 Kanallar ro'yxati:</b>\n";
        $co = 1;
        while ($row = $query->fetch_assoc()) {
            $text .= "\n<b>$co.</b> " . $row['channelLink'] . " | " . $row['channelType'];
            $co++;
        }
        $text .= "\n\n<b>Ulangan kanallar soni:</b> $soni ta";
        edit($cid2, $mid2, $text, json_encode([
            'inline_keyboard' => [
                [['text' => "🔙Ortga", 'callback_data' => "majburiy"]]
            ]
        ]));
    }else accl($qid,"Hech qanday kanallar ulanmagan!",1);
}

// <---- @obito_us ---->

if($text == "📋 Adminlar"){
if(in_array($cid,$admin)){
	if($cid == $taki_animora){
	bot('SendMessage',[
	'chat_id'=>$taki_animora,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
   [['text'=>"➕ Yangi admin qo'shish",'callback_data'=>"add"]],
   [['text'=>"📑 Ro'yxat",'callback_data'=>"list"],['text'=>"🗑 O'chirish",'callback_data'=>"remove"]],
    [['text'=>"Orqaga",'callback_data'=>"boshqarish"]]
	]
	])
	]);
	exit();
}else{	
bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
   [['text'=>"📑 Ro'yxat",'callback_data'=>"list"]],
[['text'=>"Orqaga",'callback_data'=>"boshqarish"]]
	]
	])
	]);
	exit();
}
}
}

if($data == "admins"){
if($cid2 == $taki_animora){
	bot('deleteMessage',[
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);	
bot('SendMessage',[
	'chat_id'=>$taki_animora,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
   [['text'=>"➕ Yangi admin qo'shish",'callback_data'=>"add"]],
   [['text'=>"📑 Ro'yxat",'callback_data'=>"list"],['text'=>"🗑 O'chirish",'callback_data'=>"remove"]],
	[['text'=>"Orqaga",'callback_data'=>"boshqarish"]]
	]
	])
	]);
	exit();
}else{
bot('deleteMessage',[
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);	
bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
   [['text'=>"📑 Ro'yxat",'callback_data'=>"list"]],
[['text'=>"Orqaga",'callback_data'=>"boshqarish"]]
	]
	])
	]);
	exit();
}
}

if($data == "list"){
$add = str_replace($taki_animora,"",$admins);
if($admins == $taki_animora){
	$text = "<b>Yordamchi adminlar topilmadi!</b>";
}else{
		$text = "<b>👮 Adminlar ro'yxati:</b>
$add";
}
     bot('editMessageText',[
        'chat_id'=>$cid2,
       'message_id'=>$mid2,
       'text'=>$text,
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"Orqaga",'callback_data'=>"admins"]],
]
])
]);
}

if($data == "add"){
bot('deleteMessage',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
]);
bot('SendMessage',[
'chat_id'=>$taki_animora,
'text'=>"<b>Kerakli foydalanuvchi ID raqamini yuboring:</b>",
'parse_mode'=>'html',
'reply_markup'=>$boshqarish
]);
file_put_contents("step/$cid2.step",'add-admin');
exit();
}
if($step == "add-admin" and $cid == $taki_animora){
$result = mysqli_query($connect,"SELECT * FROM user_id WHERE user_id = '" . (int)$text . "'");
$row = mysqli_fetch_assoc($result);
if(!$row){
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Ushbu foydalanuvchi botdan foydalanmaydi!</b>

Boshqa ID raqamni kiriting:",
'parse_mode'=>'html',
]);
exit();
}elseif((mb_stripos($admins, $text)!==false) or ($text != $taki_animora)){
if($admins == null){
file_put_contents("admin/admins.txt",$text);
}else{
file_put_contents("admin/admins.txt","\n".$text,FILE_APPEND);
}
bot('SendMessage',[
'chat_id'=>$taki_animora,
'text'=>"<code>$text</code> <b>adminlar ro'yxatiga qo'shildi!</b>",
'parse_mode'=>'html',
'reply_markup'=>$panel
]);
unlink("step/$cid.step");
exit();
}else{
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Ushbu foydalanuvchi adminlari ro'yxatida mavjud!</b>

Boshqa ID raqamni kiriting:",
'parse_mode'=>'html',
]);
exit();
}
}

if($data == "remove"){
bot('deleteMessage',[
'chat_id'=>$cid2,
'message_id'=>$mid2,
]);
bot('SendMessage',[
'chat_id'=>$taki_animora,
'text'=>"<b>Kerakli foydalanuvchi ID raqamini yuboring:</b>",
'parse_mode'=>'html',
'reply_markup'=>$boshqarish
]);
file_put_contents("step/$cid2.step",'remove-admin');
exit();
}
if($step == "remove-admin" and $cid == $taki_animora){
$result = mysqli_query($connect,"SELECT * FROM user_id WHERE user_id = '" . (int)$text . "'");
$row = mysqli_fetch_assoc($result);
if(!$row){
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Ushbu foydalanuvchi botdan foydalanmaydi!</b>

Boshqa ID raqamni kiriting:",
'parse_mode'=>'html',
]);
exit();
}elseif((mb_stripos($admins, $text)!==false) or ($text != $taki_animora)){
$files = file_get_contents("admin/admins.txt");
$file = str_replace("\n".$text."","",$files);
file_put_contents("admin/admins.txt",$file);
bot('SendMessage',[
'chat_id'=>$taki_animora,
'text'=>"<code>$text</code> <b>adminlar ro'yxatidan olib tashlandi!</b>",
'parse_mode'=>'html',
'reply_markup'=>$panel
]);
unlink("step/$cid.step");
exit();
}else{
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Ushbu foydalanuvchi adminlari ro'yxatida mavjud emas!</b>

Boshqa ID raqamni kiriting:",
'parse_mode'=>'html',
]);
exit();
}
}

//<---- @taki_animora ---->//

if($text == "🤖 Bot holati"){
	if(in_array($cid,$admin)){
	if($holat == "Yoqilgan"){
		$xolat = "O'chirish";
	}
	if($holat == "O'chirilgan"){
		$xolat = "Yoqish";
	}
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Hozirgi holat:</b> $holat",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
[['text'=>"$xolat",'callback_data'=>"bot"]],
[['text'=>"Orqaga",'callback_data'=>"boshqarish"]]
]
])
]);
exit();
}
}

if($data == "xolat"){
	if($holat == "Yoqilgan"){
		$xolat = "O'chirish";
	}
	if($holat == "O'chirilgan"){
		$xolat = "Yoqish";
	}
	bot('deleteMessage',[
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);
	bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>Hozirgi holat:</b> $holat",
	'parse_mode'=>'html',
	'reply_markup'=>json_encode([
	'inline_keyboard'=>[
[['text'=>"$xolat",'callback_data'=>"bot"]],
[['text'=>"Orqaga",'callback_data'=>"boshqarish"]]
]
])
]);
exit();
}

if($data == "bot"){
if($holat == "Yoqilgan"){
file_put_contents("admin/holat.txt","O'chirilgan");
     bot('editMessageText',[
        'chat_id'=>$cid2,
       'message_id'=>$mid2,
       'text'=>"<b>Muvaffaqiyatli o'zgartirildi!</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"◀️ Orqaga",'callback_data'=>"xolat"]],
]
])
]);
}else{
file_put_contents("admin/holat.txt","Yoqilgan");
     bot('editMessageText',[
        'chat_id'=>$cid2,
       'message_id'=>$mid2,
       'text'=>"<b>Muvaffaqiyatli o'zgartirildi!</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"◀️ Orqaga",'callback_data'=>"xolat"]],
]
])
]);
}
}

//<---- @taki_animora ---->//

if($text == "⚙ Asosiy sozlamalar"){
		if(in_array($cid,$admin)){
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Asosiy sozlamalar bo'limidasiz.</b>",
	'parse_mode'=>'html',
	'reply_markup'=>$asosiy,
	]);
	exit();
}
}

$delturi = file_get_contents("tizim/turi.txt");
$delmore = explode("\n",$delturi);
$delsoni = substr_count($delturi,"\n");
$key=[];
for ($delfor = 1; $delfor <= $delsoni; $delfor++) {
$title=str_replace("\n","",$delmore[$delfor]);
$key[]=["text"=>"$title - ni o'chirish","callback_data"=>"del-$title"];
$keyboard2 = array_chunk($key, 1);
$keyboard2[] = [['text'=>"➕ Yangi to'lov tizimi qo'shish",'callback_data'=>"new"]];
$pay = json_encode([
'inline_keyboard'=>$keyboard2,
]);
}

if($text == "💳 Hamyonlar"){
		if(in_array($cid,$admin)){
if($turi == null){
bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
		'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"➕ Yangi to'lov tizimi qo'shish",'callback_data'=>"new"]],
]
])
]);
exit();
}else{
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
		'reply_markup'=>$pay
]);
exit();
}
}
}

if($data == "hamyon"){
if($turi == null){
bot('deleteMessage',[ // Bu yerda xatolik yo'q, lekin linter shubhalanishi mumkin
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);
bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
		'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"➕ Yangi to'lov tizimi qo'shish",'callback_data'=>"new"]],
]
])
]);
exit();
}else{
	bot('deleteMessage',[ // Bu yerda xatolik yo'q, lekin linter shubhalanishi mumkin
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);
bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>Quyidagilardan birini tanlang:</b>",
	'parse_mode'=>'html',
		'reply_markup'=>$pay
]);
exit();
}
}

//<---- @obito_us ---->//

if(mb_stripos($data,"del-")!==false){
	$ex = explode("-",$data);
	$tur = $ex[1];
	$k = str_replace("\n".$tur."","",$turi);
   file_put_contents("tizim/turi.txt",$k);
bot('deleteMessage',[
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
	]);
bot('SendMessage',[
	'chat_id'=>$cid2,
	'text'=>"<b>To'lov tizimi o'chirildi!</b>",
		'parse_mode'=>'html',
	'reply_markup'=>$asosiy
]);
deleteFolder("tizim/$tur");
}

	/*$test = file_get_contents("step/test.txt");
   $k = str_replace("\n".$test."","",$turi);
   file_put_contents("tizim/turi.txt",$k);
deleteFolder("tizim/$test");
unlink("step/test.txt");
exit();*/

if($data == "new"){
	bot('deleteMessage',[ // Bu yerda xatolik yo'q, lekin linter shubhalanishi mumkin
	'chat_id'=>$cid2,
	'message_id'=>$mid2,
   ]);
   bot('sendMessage',[
   'chat_id'=>$cid2,
   'text'=>"<b>Yangi to'lov tizimi nomini yuboring:</b>",
   'parse_mode'=>'html',
   'reply_markup'=>$boshqarish
	]);
	file_put_contents("step/$cid2.step",'turi');
	exit();
}

if($step == "turi"){
if(in_array($cid,$admin)){
if(isset($text)){
mkdir("tizim/$text");
file_put_contents("tizim/turi.txt","$turi\n$text");
	file_put_contents("step/test.txt",$text);
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Ushbu to'lov tizimidagi hamyoningiz raqamini yuboring:</b>",
	'parse_mode'=>'html',
	]);
	file_put_contents("step/$cid.step",'wallet');
	exit();
}
}
}


if($step == "wallet"){
if(in_array($cid,$admin)){
if(is_numeric($text)=="true"){
file_put_contents("tizim/$test/wallet.txt","$wallet\n$text");
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Ushbu to'lov tizimi orqali hisobni to'ldirish bo'yicha ma'lumotni yuboring:</b>

<i>Misol uchun, \"Ushbu to'lov tizimi orqali pul yuborish jarayonida izoh kirita olmasligingiz mumkin. Ushbu holatda, biz bilan bog'laning. Havola: @obito_us</i>\"",
'parse_mode'=>'html',
	]);
	file_put_contents("step/$cid.step",'addition');
	exit();
}else{
bot('SendMessage',[
'chat_id'=>$cid,
'text'=>"<b>Faqat raqamlardan foydalaning!</b>",
'parse_mode'=>'html',
]);
exit();
}
}
}

if($step == "addition"){
		if(in_array($cid,$admin)){
	if(isset($text)){
file_put_contents("tizim/$test/addition.txt","$addition\n$text");
	bot('SendMessage',[
	'chat_id'=>$cid,
	'text'=>"<b>Yangi to'lov tizimi qo'shildi!</b>",
	'parse_mode'=>'html',
	'reply_markup'=>$asosiy,
	]);
	unlink("step/$cid.step");
	unlink("step/test.txt");
	exit();
}
}
}

if ($text == $key9) { // "✍️ Fikr bildirish" tugmasi bosilganda
    if(joinchat($cid) == false) exit();
    sms($cid, "<b>✍️ O'z fikr va takliflaringizni yuboring.</b>\n\nXabaringiz anonim tarzda adminlarga yuboriladi.", $back);
    put("step/$cid.step", "feedback_wait");
    exit();
}

if ($step == "feedback_wait") {
    if (isset($text)) {
        $feedback_text = "<b>🔔 Yangi fikr-mulohaza keldi!</b>\n\n<b>Foydalanuvchi ID:</b> <code>$cid</code>\n<b>Username:</b> @$username\n\n<b>Xabar:</b>\n" . htmlspecialchars($text);

        // Barcha adminlarga xabar yuborish
        global $admin;
        foreach ($admin as $admin_id) {
            if (!empty($admin_id)) {
                sms($admin_id, $feedback_text, null);
            }
        }
        sms($cid, "<b>✅ Fikringiz uchun rahmat! Xabaringiz adminlarga yuborildi.</b>", $menyu);
        unlink("step/$cid.step");
    }
    exit();
}

// <---- @taki_animora ---->


if($text == "🎥 Animelar sozlash" and in_array($cid,$admin)){
sms($cid,"<b>Quyidagilardan birini tanlang:</b>",json_encode([
'inline_keyboard'=>[
        [['text'=>"➕ Anime qo'shish",'callback_data'=>"add-anime"],['text'=>"➕ Manga qo'shish",'callback_data'=>"add-manga"]],
        [['text'=>"📥 Qism qo'shish",'callback_data'=>"add-episode"]],
        [['text'=>"📥 Yangi qism yuklash",'callback_data'=>"add-new-episode"]],
        [['text'=>"📝 Tahrirlash",'callback_data'=>"edit-anime"]]
]]));
exit();
}

if($data == "add-anime"){
    del();
    sms($cid2,"<b>🍿 Anime nomini kiriting:</b>",$boshqarish);
    put("step/$cid2.step","anime-name");
}

if($step == "anime-name" and in_array($cid,$admin)){
if(isset($text)){

put("step/test.txt", $text); // Tozalashni keyinroq, SQL so'rov oldidan qilamiz
$text = $connect->real_escape_string($text);
put("step/test.txt",$text);
sms($cid,"<b>🎥 Jami qismlar sonini kiriting:</b>",$boshqarish);
put("step/$cid.step","anime-episodes");
exit();
}else{
sms($cid,"<b>⚠️ Anime qo'shishda emoji va shunga o'xshash maxsus belgilardan foydalanish taqiqlangan!</b>

Qayta urining",null);
}
}

if($step == "anime-episodes" and in_array($cid,$admin)){
put("step/test2.txt", $text);
sms($cid,"<b>🌍 Qaysi davlat ishlab chiqarganini kiriting:</b>",$boshqarish);
put("step/$cid.step","anime-country");
exit();
}
if($step == "anime-country" and in_array($cid,$admin)){
if(isset($text)){
put("step/test3.txt", $text); // Tozalashni keyinroq, SQL so'rov oldidan qilamiz
sms($cid,"<b>🇺🇿 Qaysi tilda ekanligini kiriting:</b>",$boshqarish);
put("step/$cid.step","anime-language");
exit();
}
}

if($step == "anime-language" and in_array($cid,$admin)){
if(isset($text)){
put("step/test4.txt", $text); // Tozalashni keyinroq, SQL so'rov oldidan qilamiz
sms($cid,"<b>📆 Qaysi yilda ishlab chiqarilganini kiriting:</b>",$boshqarish);
put("step/$cid.step","anime-year");
exit();
}
}

if($step == "anime-year" and in_array($cid,$admin)){
put("step/test5.txt",$text);
sms($cid,"<b>🎞 Janrlarini kiriting:</b>\n\n<i>Na'muna: Drama, Fantastika, Sarguzash</i>",$boshqarish);
put("step/$cid.step","anime-fandub");
exit();
}

if($step == "anime-fandub" and in_array($cid,$admin)){
if(isset($text)){
put("step/test6.txt", $text); // Tozalashni keyinroq, SQL so'rov oldidan qilamiz
sms($cid,"<b>🎙️Fandub nomini kiriting:</b>

<i>Na'muna: @alkurtv</i>",$boshqarish);
put("step/$cid.step","anime-genre");
exit();
}
}
if ($step == "anime-genre" and in_array($cid, $admin)) {
    if (isset($text)) {

        put("step/test7.txt", $text); // Tozalashni keyinroq, SQL so'rov oldidan qilamiz
        sms($cid, "<b>🏞 Rasmini yoki 240 soniyadan oshmagan video yuboring:</b>", $boshqarish);
        put("step/$cid.step", "anime-picture");
        exit();
    }
}

if ($step == "anime-picture" and in_array($cid, $admin)) {
    if (isset($message->photo) || isset($message->video)) {
        if (isset($message->photo)) {
            $file_id = $message->photo[count($message->photo) - 1]->file_id;
        }
        elseif (isset($message->video)) {
            if ($message->video->duration <= 240) {
                $file_id = $message->video->file_id;
            } else {
                sms($cid, "<b>⚠️ Video 240 soniyadan oshmasligi kerak!</b>", $panel);
                exit();
            }
        }

        $nom = mysqli_real_escape_string($connect, get("step/test.txt"));
        $qismi = mysqli_real_escape_string($connect, get("step/test2.txt"));
        $davlati = mysqli_real_escape_string($connect, get("step/test3.txt"));
        $tili = mysqli_real_escape_string($connect, get("step/test4.txt"));
        $yili = mysqli_real_escape_string($connect, get("step/test5.txt"));
        $janri = mysqli_real_escape_string($connect, get("step/test6.txt"));
        $fandub = mysqli_real_escape_string($connect, file_get_contents("step/test7.txt"));
        $date = date('H:i d.m.Y');

        if ($connect->query("INSERT INTO `animelar` (`nom`, `rams`, `qismi`, `davlat`, `tili`, `yili`, `janri`, `qidiruv`,`aniType`, `sana`) VALUES ('$nom', '$file_id', '$qismi', '$davlati', '$tili', '$yili', '$janri', '0', '$fandub', '$date')")) {

 $code = $connect->insert_id;
 sms($cid, "<b>✅ Anime qo'shildi!</b>\n\n<b>Anime kodi:</b> <code>$code</code>", $panel);

 // Fayllarni o'chirish
 unlink("step/$cid.step");
 unlink("step/test.txt");
 unlink("step/test2.txt");
            unlink("step/test3.txt");
            unlink("step/test4.txt");
            unlink("step/test5.txt");
            unlink("step/test6.txt");
            unlink("step/test7.txt");
            exit();
        } else {
            sms($cid, "<b>⚠️ Xatolik!</b>\n\n<code>$connect->error</code>", $panel);

            // Fayllarni o'chirish
            unlink("step/$cid.step");
            unlink("step/test.txt");
            unlink("step/test2.txt");
            unlink("step/test3.txt");
            unlink("step/test4.txt");
            unlink("step/test5.txt");
            unlink("step/test6.txt");
            unlink("step/test7.txt");
            exit();
        }
    } else {
        sms($cid, "<b>⚠️ Iltimos, rasm yoki 60 soniyadan oshmagan video yuboring!</b>", $panel);
    }
}

if ($text == "✅ Tugatish" and in_array($cid, $admin) and strpos($step, "add-episode-continuous") !== false) {
    unlink("step/$cid.step");
    unlink("step/test.txt");
    sms($cid, "<b>✅ Qism qo'shish jarayoni yakunlandi.</b>", $panel);
    exit();
}


if($data == "add-episode"){
del();

sms($cid2,"<b>🔢 Qism qo'shmoqchi bo'lgan anime kodini kiriting:</b>", $boshqarish);
put("step/$cid2.step","add-episode-continuous-id");
}

if($step == "add-episode-continuous-id" and in_array($cid,$admin)){
if(is_numeric($text)){
    $safe_id = (int)$text;
    $check_anime = mysqli_query($connect, "SELECT id, nom, type FROM animelar WHERE id = $safe_id");
    if(mysqli_num_rows($check_anime) > 0){

        $anime = mysqli_fetch_assoc($check_anime);
        $is_manga = ($anime['type'] == 'manga');
        $item_type_text = $is_manga ? "Manga" : "Anime";
        $file_type_text = $is_manga ? "PDF fayllarni" : "videolarni";

        put("step/test.txt", $safe_id);
        sms($cid, "<b>$item_type_text:</b> {$anime['nom']}\n\n<b>📂 Endi qismlarni ($file_type_text) ketma-ket yuboring.</b>\n\n<i>Jarayonni to'xtatish uchun '✅ Tugatish' tugmasini bosing.</i>", json_encode([
            'resize_keyboard'=>true,
            'keyboard'=>[
                [['text'=>"✅ Tugatish"]]
            ]
        ]));
        put("step/$cid.step", "add-episode-continuous-video");
    } else {
        sms($cid, "<b>❌ Bu kodga ega anime topilmadi.</b>", null);
    }
    exit();
}
}

if($data == "add-new-episode"){
    del();

    sms($cid2,"<b>🔢 Yangi qism qo'shmoqchi bo'lgan anime kodini kiriting:</b>", $boshqarish);
    put("step/$cid2.step","new-episode-code");
}

if($step == "new-episode-code" and in_array($cid,$admin)){
    if(is_numeric($text)){
        $safe_id = (int)$text;
        
        $anime_res = mysqli_query($connect, "SELECT nom, qismi, type FROM animelar WHERE id = $safe_id");
        if(mysqli_num_rows($anime_res) > 0){
            $anime = mysqli_fetch_assoc($anime_res);
            $is_manga = ($anime['type'] == 'manga');
            $qismi_str = $anime['qismi'];
            
            // "16/??" yoki "16/150" formatini tekshirish
            if (preg_match('/(\d+)\/(.+)/', $qismi_str, $matches)) {
                $current_ep = (int)$matches[1];
                $next_ep = $current_ep + 1;
                $total_ep = $matches[2]; // '??' yoki raqam bo'lishi mumkin
                $new_qismi_str = "$next_ep/$total_ep";
            } else {
                // Agar format mos kelmasa, bazadan oxirgi qismni topishga harakat qilish
                $last_ep_res = mysqli_query($connect, "SELECT qism FROM anime_datas WHERE id = $safe_id ORDER BY CAST(qism AS UNSIGNED) DESC, qism DESC LIMIT 1");
                $last_ep_row = mysqli_fetch_assoc($last_ep_res);
                $next_ep = is_numeric($last_ep_row['qism']) ? (int)$last_ep_row['qism'] + 1 : "Yangi";
                $new_qismi_str = null; // Avtomatik yangilanmaydi
            }
            
            $upload_type_text = $is_manga ? "PDF faylni" : "videoni";
            put("step/test.txt", $safe_id); // anime id
            put("step/test2.txt", $next_ep); // yangi qism raqami
            if ($new_qismi_str) {
                put("step/test3.txt", $new_qismi_str); // yangilanadigan jami qismlar soni
            }

            sms($cid, "<b>Anime/Manga:</b> {$anime['nom']}\n<b>Yuklanadigan qism:</b> $next_ep\n\n<b>📂 Endi ushbu epizod uchun $upload_type_text yuboring:</b>", $boshqarish);
            put("step/$cid.step","episode-video");
        } else {
            sms($cid, "<b>❌ Bu kodga ega anime topilmadi.</b>", null);
        }
    } else {
        sms($cid, "<b>❌ Faqat raqamli kod kiriting.</b>", null);
    }
    exit();
}

if (strpos($data, "sendNewEpTo=") !== false) {
    del();
    list($kanal, $id) = explode("|", explode("=", $data)[1]);
    $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM animelar WHERE id = " . (int)$id));
    if ($kanal == "ALL") {
        global $kanallar;
        foreach ($kanallar as $kanal_name) {
            sendAnimePost($kanal_name, $rew, null);
        }
        sms($cid2, "✅ Yangi qism barcha kanallarga muvaffaqiyatli yuborildi!", $panel);
    } else {
        sendAnimePost($kanal, $rew, null);
        sms($cid2, "✅ Yangi qism <b>$kanal</b> ga muvaffaqiyatli yuborildi!", $panel);
    }
}

if($step == "episode-video" and in_array($cid,$admin)){
    if(isset($message->video) || isset($message->document)){

        $file_id = null;
        $file_type = null;

        if (isset($message->document) && $message->document->mime_type == 'application/pdf') {
            $file_id = $message->document->file_id;
            $file_type = 'document';
        } elseif (isset($message->video)) {
            $file_id = $message->video->file_id;
            $file_type = 'video';
        } else {
            sms($cid, "<b>❌ Noto'g'ri format!</b>\n\nIltimos, faqat PDF fayl yoki video yuboring.", $boshqarish);
            exit();
        }

        $id = mysqli_real_escape_string($connect, get("step/test.txt"));
        $yangi_qism_raqami = mysqli_real_escape_string($connect, get("step/test2.txt"));
        $yangilanadigan_qismlar = get("step/test3.txt"); // "new-episode-code" dan keladi
        $sana = date('H:i:s d.m.Y');
        $admin_message = ""; // Xabar matnini oldindan e'lon qilish
		    
        $anime_info = mysqli_fetch_assoc(mysqli_query($connect, "SELECT nom FROM animelar WHERE id = " . (int)$id));
        $anime_nomi = $anime_info['nom'] ?? 'Noma\'lum anime'; //NOSONAR

        $notification_text = "🔔 <b>\"$anime_nomi\" animesiga yangi `$yangi_qism_raqami`-qism qo'shildi!</b>\n\nKo'rish uchun bosing: /start $id";

        $subscribers_query = mysqli_query($connect, "SELECT user_id FROM user_watchlist WHERE anime_id = " . (int)$id);
        $subscriber_ids = [];
        while ($subscriber = mysqli_fetch_assoc($subscribers_query)) {
            $subscriber_ids[] = $subscriber['user_id'];
        }

        if (!empty($subscriber_ids)) {
            multi_curl_send($subscriber_ids, $notification_text);
            $admin_message .= "\n\n🔔 Bildirishnoma <b>" . count($subscriber_ids) . "</b> ta obunachiga yuborildi.";
        }

        if ($connect->query("INSERT INTO anime_datas(id, file_id, qism, sana, type) VALUES ('$id', '$file_id', '$yangi_qism_raqami', '$sana', '$file_type')")) {
            $admin_message = "<b>✅ `$id` raqamli animega `$yangi_qism_raqami`-qism yuklandi!</b>";
            
            // Agar "Yangi qism yuklash" orqali qo'shilgan bo'lsa, jami qismlar sonini yangilash
            if ($yangilanadigan_qismlar) {
                mysqli_query($connect, "UPDATE animelar SET qismi = '" . mysqli_real_escape_string($connect, $yangilanadigan_qismlar) . "' WHERE id = " . (int)$id);
                $admin_message .= "\n\n📊 Jami qismlar soni <b>`$yangilanadigan_qismlar`</b> ga yangilandi.";
            }


            // Kanallarga yuborish tugmalarini yaratish
            $kanal_buttons = [];
            foreach ($kanallar as $kanal) {
                $kanal_buttons[] = [['text' => "📤 $kanal ga yuborish", 'callback_data' => "sendNewEpTo=$kanal|$id"]];
            }
            $kanal_buttons[] = [['text' => "📡 BARCHA kanallarga yuborish", 'callback_data' => "sendNewEpTo=ALL|$id"]];
            $kanal_keyboard = json_encode(['inline_keyboard' => $kanal_buttons]);

            sms($cid, $admin_message, null);
            sms($cid, "📬 Yangi qismni qaysi kanalga yuboramiz?", $kanal_keyboard);
        } else {
            sms($cid, "<b>⚠️ Xatolik!</b>\n\n<code>" . $connect->error . "</code>", $panel);
        }

        // Jarayon tugagach vaqtinchalik fayllarni o'chirish
        unlink("step/$cid.step");
        unlink("step/test.txt");

        if (file_exists("step/test2.txt")) unlink("step/test2.txt");
        if (file_exists("step/test3.txt")) unlink("step/test3.txt");
        exit();
    }
}

if($step == "add-episode-continuous-video" and in_array($cid,$admin)){
    if(isset($message->video) || isset($message->document)){

        $file_id = null;
        $file_type = null;

        if (isset($message->document) && $message->document->mime_type == 'application/pdf') {
            $file_id = $message->document->file_id;
            $file_type = 'document';
        } elseif (isset($message->video)) {
            $file_id = $message->video->file_id;
            $file_type = 'video';
        } else {
            sms($cid, "<b>❌ Noto'g'ri format!</b>\n\nIltimos, faqat PDF fayl yoki video yuboring.", null);
            exit();
        }

        $id = mysqli_real_escape_string($connect, get("step/test.txt"));

        // Keyingi qism raqamini avtomatik aniqlash
        $last_ep_res = mysqli_query($connect, "SELECT qism FROM anime_datas WHERE id = " . (int)$id . " ORDER BY CAST(qism AS UNSIGNED) DESC, qism DESC LIMIT 1");
        $last_ep_row = mysqli_fetch_assoc($last_ep_res);
        $next_ep = (mysqli_num_rows($last_ep_res) > 0 && is_numeric($last_ep_row['qism'])) ? (int)$last_ep_row['qism'] + 1 : 1;
        $sana = date('H:i:s d.m.Y');

        if ($connect->query("INSERT INTO anime_datas(id, file_id, qism, sana, type) VALUES ('$id', '$file_id', '$next_ep', '$sana', '$file_type')")) {
            sms($cid, "<b>✅ `$id` raqamli animega `$next_ep`-qism yuklandi. Keyingisini yuboring...</b>", null);
        } else {
            sms($cid, "<b>⚠️ Xatolik!</b>\n\n<code>" . $connect->error . "</code>", null);
        }
        exit();
    }
}


// Watchlist va Rating uchun callback handlerlar
if (strpos($data, "watchlist_add=") === 0) {
    $anime_id = (int)str_replace("watchlist_add=", "", $data);
    $user_id = $cid2;

    $check_sql = "SELECT * FROM user_watchlist WHERE user_id = $user_id AND anime_id = $anime_id";
    $result = mysqli_query($connect, $check_sql);

    if (mysqli_num_rows($result) > 0) {
        accl($qid, "✅ Bu anime saqlanganlar ro'yxatingizda mavjud!", true);
    } else {
        $insert_sql = "INSERT INTO user_watchlist (user_id, anime_id) VALUES ($user_id, $anime_id)";
        if (mysqli_query($connect, $insert_sql)) {
            accl($qid, "❤️ Anime saqlanganlar ro'yxatiga qo'shildi!", true);
        } else {
            accl($qid, "❌ Xatolik yuz berdi!", true);
        }
    }
    exit();
}

function show_watchlist($chat_id, $page = 1, $message_id = null, $is_delete_mode = false) {
    global $connect, $qid;
    $user_id = (int)$chat_id;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Umumiy saqlangan animelar sonini olish
    $count_query = mysqli_query($connect, "SELECT COUNT(*) as total FROM user_watchlist WHERE user_id = $user_id");
    $total_items = mysqli_fetch_assoc($count_query)['total'];
    $total_pages = ceil($total_items / $limit);

    if ($total_items == 0) {
        $text_response = "Siz hali hech qanday anime saqlamadingiz.";
        $keyboard = [[['text' => "❌ Yopish", 'callback_data' => "close"]]];
        if ($message_id) {
            edit($chat_id, $message_id, $text_response, json_encode(['inline_keyboard' => $keyboard]));
        } else {
            sms($chat_id, $text_response, json_encode(['inline_keyboard' => $keyboard]));
        }
        return;
    }

    $query = "SELECT a.id, a.nom FROM animelar a JOIN user_watchlist w ON a.id = w.anime_id WHERE w.user_id = $user_id ORDER BY w.added_at DESC LIMIT $limit OFFSET $offset";
    $result = mysqli_query($connect, $query);

    $buttons = [];
    if ($is_delete_mode) {
        $text_response = "<b>🗑 O'chirmoqchi bo'lgan animeni tanlang:</b>";
        while ($row = mysqli_fetch_assoc($result)) {
            $buttons[] = [['text' => "🗑 {$row['nom']}", 'callback_data' => "watchlist_delete_item={$row['id']}&page={$page}"]];
        }
    } else {
        $text_response = "<b>❤️ Siz saqlagan animelar:</b>";
        $i = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $buttons[] = [['text' => "{$i}. {$row['nom']}", 'callback_data' => "anime={$row['id']}"]];
            $i++;
        }
    }

    // Pagination buttons
    $pagination_buttons = [];
    if ($page > 1) {
        $prev_page = $page - 1;
        $callback = $is_delete_mode ? "watchlist_delete_mode=$prev_page" : "watchlist_show=$prev_page";
        $pagination_buttons[] = ['text' => "⬅️ Oldingi", 'callback_data' => $callback];
    }
    if ($total_pages > 1) {
        $pagination_buttons[] = ['text' => "$page/$total_pages", 'callback_data' => "null"];
    }
    if ($page < $total_pages) {
        $next_page = $page + 1;
        $callback = $is_delete_mode ? "watchlist_delete_mode=$next_page" : "watchlist_show=$next_page";
        $pagination_buttons[] = ['text' => "Keyingi ➡️", 'callback_data' => $callback];
    }
    if (!empty($pagination_buttons)) {
        $buttons[] = $pagination_buttons;
    }

    // Action buttons
    if ($is_delete_mode) {
        $buttons[] = [['text' => "◀️ Orqaga", 'callback_data' => "watchlist_show=$page"]];
    } else {
        $buttons[] = [['text' => "🗑 O'chirish", 'callback_data' => "watchlist_delete_mode=1"], ['text' => "❌ Yopish", 'callback_data' => "close"]];
    }

    if ($message_id) {
        edit($chat_id, $message_id, $text_response, json_encode(['inline_keyboard' => $buttons]));
    } else {
        sms($chat_id, $text_response, json_encode(['inline_keyboard' => $buttons]));
    }
}

if ($text == "❤️ Saqlanganlar") {
    if(joinchat($cid) == false) exit();
    show_watchlist($cid, 1);
    exit();
}

if (strpos($data, "watchlist_show=") === 0) {
    $page = (int)str_replace("watchlist_show=", "", $data);
    show_watchlist($cid2, $page, $mid2);
    if(joinchat($cid2) == false) exit();
    exit();
}

if (strpos($data, "watchlist_delete_mode=") === 0) {
    $page = (int)str_replace("watchlist_delete_mode=", "", $data);
    show_watchlist($cid2, $page, $mid2, true);
    if(joinchat($cid2) == false) exit();
    exit();
}

if (strpos($data, "watchlist_delete_item=") === 0) {
    parse_str(str_replace("watchlist_delete_item=", "", $data), $params);
    $anime_id = (int)$params['watchlist_delete_item'];
    if(joinchat($cid2) == false) exit();
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $user_id = $cid2;

    $delete_sql = "DELETE FROM user_watchlist WHERE user_id = $user_id AND anime_id = $anime_id";
    if (mysqli_query($connect, $delete_sql)) {
        accl($qid, "✅ Anime saqlanganlardan o'chirildi!", false);
        show_watchlist($cid2, $page, $mid2, true); // Oynani yangilash
    } else {
        accl($qid, "❌ Xatolik yuz berdi!", true);
    }
    exit();
}

if ($data == "edit-anime") {
	edit($cid2, $mid2, "<b>Tahrirlamoqchi bo'lgan animeni tanlang:</b>", json_encode([


		'inline_keyboard' => [
			[['text' => "Anime ma'lumotlarini", 'callback_data' => "editType-animes"]],
			[['text' => "Anime qismini", 'callback_data' => "editType-anime_datas"]]
		]
	]));
}

if (mb_stripos($data, "editType-") !== false) {
	$ex = explode("-", $data)[1];
	put("step/$cid2.tip", $ex);
	del();
	sms($cid2, "<b>Anime kodini kiriting:</b>", $boshqarish);
	put("step/$cid2.step", "edit-anime");
}

if($step == "edit-anime"){
    $tip=get("step/$cid.tip");
    if($tip == "animes"){
        $result=mysqli_query($connect,"SELECT * FROM animelar WHERE id = " . (int)$text);
        $row=mysqli_fetch_assoc($result);
        if($row){
            $kb=json_encode([
            'inline_keyboard'=>[
            [['text'=>"Nomini tahrirlash",'callback_data'=>"editAnime-nom-$text"]],
            [['text'=>"Qismini tahrirlash",'callback_data'=>"editAnime-qismi-$text"]],
            [['text'=>"Davlatini tahrirlash",'callback_data'=>"editAnime-davlat-$text"]],
            [['text'=>"Tilini tahrirlash",'callback_data'=>"editAnime-tili-$text"]],
            [['text'=>"Yilini tahrirlash",'callback_data'=>"editAnime-yili-$text"]],
            [['text'=>"Janrini tahrirlash",'callback_data'=>"editAnime-janri-$text"]],
            [['text'=>"Anime rasmini tahrirlash",'callback_data'=>"editAnime-image-$text"]],
            [['text'=>"Animeni o'chirish",'callback_data'=>"editAnime-delete-$text"]]
            ]]);
            sms($cid,"<b>❓ Nimani tahrirlamoqchisiz?</b>",$kb);
            unlink("step/$cid.step");
            exit();
        }else{
            sms($cid,"<b>❗ Anime mavjud emas, qayta urinib ko'ring!</b>",null);
            exit();
        }
    }else{ // $tip == "anime_datas"
        $result=mysqli_query($connect,"SELECT * FROM animelar WHERE id = " . (int)$text);
        if(mysqli_num_rows($result) > 0){
            sms($cid,"<b>Tahrirlamoqchi bo'lgan qism raqamini yuboring:</b>\n\n<i>Masalan: <code>12</code> yoki <code>856/999</code></i>",$boshqarish);
            put("step/$cid.step","anime-epEdit=$text"); // Anime ID ni saqlash
            exit();
        }else{
            sms($cid,"<b>❗ Anime mavjud emas, qayta urinib ko'ring!</b>",null);
            exit();
        }
    }
}


if(mb_stripos($step,"anime-epEdit=")!==false){
    $ex = explode("=",$step);
    $id = (int)$ex[1];
    $qism_raqami_safe = mysqli_real_escape_string($connect, $text);

    $result=mysqli_query($connect,"SELECT * FROM anime_datas WHERE id = $id AND qism = '$qism_raqami_safe'");
    if(mysqli_num_rows($result) > 0){
        $kb=json_encode([
        'inline_keyboard'=>[
        [['text'=>"Qism raqamini tahrirlash",'callback_data'=>"editEpisode-qism-$id-" . urlencode($text)]],
        [['text'=>"Videoni tahrirlash",'callback_data'=>"editEpisode-file_id-$id-" . urlencode($text)]],
        ]]);
        sms($cid,"<b>❓ Nimani tahrirlamoqchisiz?</b>\n\nAnime ID: <code>$id</code>\nQism: <code>$text</code>",$kb);
        unlink("step/$cid.step");
        exit();
    }else{
        sms($cid,"<b>❗ Ushbu animeda `$text`-qism mavjud emas, qayta urinib ko'ring.</b>",null);
        exit();
    }
}

if(mb_stripos($data,"editAnime-")!==false){
del();
sms($cid2,"<b>Yangi qiymatini kiriting:</b>",$boshqarish);
put("step/$cid2.step",$data);
}



if (mb_stripos($step, "editAnime-") !== false) {
    $ex = explode("-", $step);
    $tip = $ex[1];
    $id = $ex[2];

    if ($tip == "delete") {
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "✅ Ha", 'callback_data' => "confirm-delete-$id"],
                    ['text' => "❌ Yo‘q", 'callback_data' => "cancel-delete"]
                ]
            ]
        ]);
        sms($cid, "❗ Ushbu animeni va uning barcha qismlarini o'chirishni istaysizmi?\nID: <b>$id</b>", $keyboard);
        exit();
    }

    if ($tip == "image") {
        if (isset($message->photo) or isset($message->video)) {
            if (isset($message->photo)) {
                $file_id = $message->photo[count($message->photo) - 1]->file_id;
            } elseif (isset($message->video)) {
                if ($message->video->duration <= 240) {
                    $file_id = $message->video->file_id;
                } else {
                    sms($cid, "<b>⚠️ Video 240 soniyadan oshmasligi kerak!</b>", $panel);
                    exit();
                }
            }

            $query = "UPDATE animelar SET rams = '" . mysqli_real_escape_string($connect, $file_id) . "' WHERE id = $id";
            if (mysqli_query($connect, $query)) {
                sms($cid, "<b>✅ Rasm muvaffaqiyatli yangilandi!</b>", $panel);
            } else {
                sms($cid, "<b>❗ Rasmni yangilashda xatolik yuz berdi!</b>", $panel);
            }
            exit();
        } else {
            sms($cid, "<b>⚠️ Iltimos, rasm yoki 240 soniyadan oshmagan video yuboring!</b>", $panel);
            exit();
        }
    } else {
        mysqli_query($connect, "UPDATE animelar SET `$tip`='" . mysqli_real_escape_string($connect, $text) . "' WHERE id = " . (int)$id);
        sms($cid, "<b>✅ Saqlandi.</b>", $panel);
        unlink("step/$cid.step");
        exit();
    }
}

if (mb_stripos($data, "confirm-delete-") !== false) {
    $ex = explode("-", $data);
    $id = $ex[2];
    $safe_id = (int)$id;
    $check = mysqli_query($connect, "SELECT * FROM animelar WHERE id = $safe_id");
    if (mysqli_num_rows($check) > 0) {

        $deleteAnime = mysqli_query($connect, "DELETE FROM animelar WHERE id = $safe_id");
        $deleteEpisodes = mysqli_query($connect, "DELETE FROM anime_datas WHERE id = $safe_id");

        if ($deleteAnime) {
            edit($cid2, $mid2, "<b>✅ Anime va barcha qismlari muvaffaqiyatli o'chirildi!</b>",null);
        } else {
            edit($cid2, $mid2, "<b>❗ O'chirishda xatolik yuz berdi!</b>",null);
        }

    } else {
        edit($cid2, $mid2, "<b>❗ Bunday ID ga ega anime topilmadi.</b>",null);
    }

    exit();
}


if ($data == "cancel-delete") {
    edit($cid2, $mid2, "<b>❌ O‘chirish  so'rovi bekor qilindi.</b>",null);
    exit();
}


if(mb_stripos($data,"editEpisode-")!==false){
del();
sms($cid2,"<b>Yangi qiymatini kiriting:</b>",$boshqarish);
put("step/$cid2.step",$data);
}

if(mb_stripos($step,"editEpisode-")!==false){
$ex = explode("-",$step);
$tip = $ex[1];
$id = $ex[2];
$qism_raqami = urldecode($ex[3]);
if($tip=="file_id"){
if(isset($message->video)){
$file_id = $message->video->file_id;
mysqli_query($connect,"UPDATE anime_datas SET `file_id`='" . mysqli_real_escape_string($connect, $file_id) . "' WHERE id = " . (int)$id . " AND qism = '" . mysqli_real_escape_string($connect, $qism_raqami) . "'");
sms($cid,"<b>✅ Video muvaffaqiyatli yangilandi.</b>",null);
unlink("step/$cid.step");
exit();
}else{
sms($cid,"<b>❗Faqat videodan foydalaning.</b>",null);
exit();
}
}else{
mysqli_query($connect,"UPDATE anime_datas SET `$tip`='" . mysqli_real_escape_string($connect, $text) . "' WHERE id = " . (int)$id . " AND qism = '" . mysqli_real_escape_string($connect, $qism_raqami) . "'");
sms($cid,"<b>✅ Saqlandi.</b>",null);
unlink("step/$cid.step");
exit();
}

}

// <---- @taki_animora ---->

$valyuta = file_get_contents("admin/valyuta.txt");
$narx = file_get_contents("admin/vip.txt");
$studio_name = file_get_contents("admin/studio_name.txt");

$name_content = ($content == "false") ? "🔒 Kontent cheklash" : "🔓 Kontent ulashish";


if ($text == "*️⃣ Birlamchi sozlamalar") {
    if (in_array($cid, $admin)) {
        sms($cid, "<b>Hozirgi birlamchi sozlamalar:</b>

<i>1. Valyuta - $valyuta
2. VIP narxi - $narx $valyuta
3. Studia nomi - $studio_name</i>", json_encode([
            'inline_keyboard' => [
                [['text' => "1", 'callback_data' => "valyuta"], ['text' => "2", 'callback_data' => "vnarx"], ['text' => "3", 'callback_data' => "studio_name"]],
                [['text'=>$name_content,'callback_data'=>"content"]],
            ]
        ]));
        exit();
    }
}


if ($data == "content"){
    if ($content == "true"){
        put("tizim/content.txt",'false');
        edit($cid2,$mid2,"<b>$name_content  muvoffaqatli yoqildi ✅</b>",null);
    }elseif ($content == "false") {
        put("tizim/content.txt",'true');
        edit($cid2,$mid2,"<b>$name_content  muvoffaqatli yoqildi ✅</b>",null);
    }
}

if ($data == "birlamchi") {
    edit($cid2, $mid2, "<b>Hozirgi birlamchi sozlamalar:</b>

<i>1. Valyuta - $valyuta
2. VIP narxi - $narx $valyuta
3. Studia nomi - $studio_name</i>", json_encode([
        'inline_keyboard' => [
            [['text' => "1", 'callback_data' => "valyuta"], ['text' => "2", 'callback_data' => "vnarx"], ['text' => "3", 'callback_data' => "studio_name"]],
        ]
    ]));
    exit();
}

if ($data == "valyuta") {
    del();
    sms($cid2, "📝 <b>Yangi valyutani kiriting:</b>", $boshqarish);
    put("step/$cid2.step", 'valyuta');
    exit();
}

if ($step == "valyuta" and in_array($cid, $admin)) {
    if (isset($text)) {
        put("admin/valyuta.txt", $text);
        sms($cid, "<b>✅ Valyuta saqlandi.</b>", $panel);
        unlink("step/$cid.step");
        exit();
    }
}

if ($data == "vnarx") {
    del();
    sms($cid2, "📝 <b>Yangi VIP narxni kiriting:</b>", $boshqarish);
    put("step/$cid2.step", 'vnarx');
    exit();
}

if ($step == "vnarx" and in_array($cid, $admin)) {
    if (isset($text)) {
        put("admin/vip.txt", $text);
        sms($cid, "<b>✅ VIP narx saqlandi.</b>", $panel);
        unlink("step/$cid.step");
        exit();
    }
}

if ($data == "studio_name") {
    del();
    sms($cid2, "📝 <b>Yangi studia nomini kiriting:</b>", $boshqarish);
    put("step/$cid2.step", 'studio_name');
    exit();
}

if ($step == "studio_name" and in_array($cid, $admin)) {
    if (isset($text)) {
        put("admin/studio_name.txt", $text);
        sms($cid, "<b>✅ Studia nomi saqlandi.</b>", $panel);
        unlink("step/$cid.step");
        exit();
    }
}
// <---- @obito_us ---->

if($text == "📃 Matnlar" and in_array($cid,$admin)){
sms($cid,"<b>Quyidagilardan birini tanlang:</b>",json_encode([
'inline_keyboard'=>[
[['text'=>"Boshlang'ich matni",'callback_data'=>"matn1"]],
[['text'=>"Qo'llanma",'callback_data'=>"matn2"]],
[['text'=>"🔖 Homiy matni",'callback_data'=>"matn5"]],
[['text'=>"🏆 Konkurs matni",'callback_data'=>"matn_konkurs"]],
]]));
exit();
}

if($data == "matn1"){
del();
sms($cid2,"<b>Boshlang'ich matnini yuboring:</b>",$boshqarish);
put("step/$cid2.step",'matn1');
exit();
}

if($step == "matn1" and in_array($cid,$admin)){
if(isset($text)){
put("matn/start.txt",$text);
sms($cid,"<b>✅ Saqlandi.</b>",$panel);
unlink("step/$cid.step");
exit();

}
}

if($data == "matn2"){
del();
sms($cid2,"<b>Qo'llanma matnini yuboring::</b>",$boshqarish);
put("step/$cid2.step",'matn2');
exit();
}

if($step == "matn2" and in_array($cid,$admin)){
if(isset($text)){
put("matn/qollanma.txt",$text);
sms($cid,"<b>✅ Saqlandi.</b>",$panel);
unlink("step/$cid.step");
exit();
}
}

if($data == "matn5"){
del();
sms($cid2,"<b>Homiy matnini yuboring:</b>",$boshqarish);
put("step/$cid2.step",'matn5');
exit();
}

if($step == "matn5" and in_array($cid,$admin)){
if(isset($text)){
put("matn/homiy.txt",$text);
sms($cid,"<b>✅ Saqlandi.</b>",$panel);
unlink("step/$cid.step");
exit();
}
}

if($data == "matn_konkurs"){
del();
sms($cid2,"<b>Konkurs matnini yuboring:</b>\n\nFoydalanish mumkin bo'lgan o'zgaruvchilar:\n<code>%odam</code> - foydalanuvchi taklif qilgan odamlar soni\n<code>%havola</code> - foydalanuvchining referal havolasi",$boshqarish);
put("step/$cid2.step",'matn_konkurs');
exit();
}

if($step == "matn_konkurs" and in_array($cid,$admin)){
if(isset($text)){
put("matn/konkurs.txt",$text);
sms($cid,"<b>✅ Saqlandi.</b>",$panel);
unlink("step/$cid.step");
exit();
}
}


// <---- @taki_animora ---->


if($text == "🎛 Tugmalar" and in_array($cid,$admin)){
sms($cid,"<b>Quyidagilardan birini tanlang:</b>",json_encode([
'inline_keyboard'=>[
[['text'=>"🖥 Asosiy menyudagi tugmalar",'callback_data'=>"asosiy"]],
[['text'=>"⚠️ O'z holiga qaytarish",'callback_data'=>"reset"]],
]]));
exit();
}

if($data == "tugmalar"){
del();
sms($cid2,"<b>Quyidagilardan birini tanlang:</b>",json_encode([
'inline_keyboard'=>[
[['text'=>"🖥 Asosiy menyudagi tugmalar",'callback_data'=>"asosiy"]],
[['text'=>"⚠️ O'z holiga qaytarish",'callback_data'=>"reset"]],
]]));
exit();
}


if($data == "reset"){
edit($cid2,$mid2,"<b>Barcha tahrirlangan tugmalar bilan bog'liq sozlamalar o'chirib yuboriladi va birlamchi sozlamalar o'rnatiladi.</b>

<i>Ushbu jarayonni davom ettirsangiz, avvalgi sozlamalarni tiklay olmaysiz, rozimisiz?</i>",json_encode([
'inline_keyboard'=>[
[['text'=>"✅ Roziman",'callback_data'=>'roziman']],
[['text'=>"◀️ Orqaga",'callback_data'=>"tugmalar"]],
]]));
}

if($data == "roziman"){
edit($cid2,$mid2,"<b>Tugma sozlamalari o'chirilib, birlamchi sozlamalar o'rnatildi.</b>",json_encode([
'inline_keyboard'=>[
[['text'=>"Orqaga",'callback_data'=>"tugmalar"]],
]]));
deleteFolder("tugma");
}

if($data == "asosiy"){	
edit($cid2,$mid2,"<b>Quyidagilardan birini tanlang:</b>",json_encode([
'inline_keyboard'=>[
[['text'=>$key1,'callback_data'=>"tugma=key1"],['text'=>$key9,'callback_data'=>"tugma=key9"]], //NOSONAR
[['text'=>$key2,'callback_data'=>"tugma=key2"],['text'=>$key3,'callback_data'=>"tugma=key3"],['text'=>$key8,'callback_data'=>"tugma=key8"]], //NOSONAR
[['text'=>$key4,'callback_data'=>"tugma=key4"],['text'=>$key5,'callback_data'=>"tugma=key5"]],
[['text'=>$key6,'callback_data'=>"tugma=key6"]],
[['text'=>$key7,'callback_data'=>"tugma=key7"]],
[['text'=>"Orqaga",'callback_data'=>"tugmalar"]]
]]));
}

if(mb_stripos($data,"tugma=")!==false){
del();
sms($cid2,"<b>Tugma uchun yangi nom yuboring:</b>",$boshqarish);
put("step/$cid2.step",$data);
exit();
}

if(mb_stripos($step,"tugma=")!==false and in_array($cid,$admin)){
$tip=explode("=",$step)[1];
if(isset($text)){
put("tugma/$tip.txt",$text);
sms($cid,"<b>Qabul qilindi!</b>

<i>Tugma nomi</i> <b>$text</b> <i>ga o'zgartirildi.</i>",$panel);
unlink("step/$cid.step");
exit();
}
}

// Bu blok faqat matnli xabar kelganda, hech qanday callback_data va step bo'lmaganda ishlashi kerak
if (isset($message) and !isset($data) and empty($step)) {
    // Bu blokni bo'sh qoldiramiz, chunki qidiruv endi maxsus step orqali amalga oshiriladi.
    // Bu tasodifiy matnlarga botning javob berishini oldini oladi.
}

if($text == "🏆 Konkurs" and in_array($cid, $admin)){
    $konkurs_holati = file_get_contents("admin/konkurs_holati.txt");
    if($konkurs_holati == "on"){
        $holat_text = "✅ Yoqilgan";
        $button_text = "❌ O'chirish";
        $button_data = "konkurs_off";
    }else{
        $holat_text = "❌ O'chirilgan";
        $button_text = "✅ Yoqish";
        $button_data = "konkurs_on";
    }

    sms($cid, "<b>🏆 Konkursni boshqarish paneli</b>\n\nHozirgi holat: <b>$holat_text</b>", json_encode([
        'inline_keyboard' => [
            [['text' => $button_text, 'callback_data' => $button_data]],
            [['text' => "📊 Top 10 reyting", 'callback_data' => "konkurs_top"]],
            [['text' => "◀️ Orqaga", 'callback_data' => "boshqarish"]]
        ]
    ]));
}

if($data == "konkurs_on" and in_array($cid2,$admin)){
    file_put_contents("admin/konkurs_holati.txt", "on");
    edit($cid2, $mid2, "<b>🏆 Konkurs muvaffaqiyatli yoqildi!</b>", json_encode([
        'inline_keyboard' => [
            [['text' => "❌ O'chirish", 'callback_data' => "konkurs_off"]],
            [['text' => "📊 Top 10 reyting", 'callback_data' => "konkurs_top"]],
        ]
    ]));
}

if($data == "konkurs_off" and in_array($cid2,$admin)){
    file_put_contents("admin/konkurs_holati.txt", "off");
    edit($cid2, $mid2, "<b>🏆 Konkurs muvaffaqiyatli o'chirildi!</b>", json_encode([
        'inline_keyboard' => [
            [['text' => "✅ Yoqish", 'callback_data' => "konkurs_on"]],
            [['text' => "📊 Top 10 reyting", 'callback_data' => "konkurs_top"]],
        ]
    ]));
}

if($text == $key7 and !in_array($cid, $admin)){
    if(joinchat($cid) == false) exit();
    $konkurs_holati = file_get_contents("admin/konkurs_holati.txt");
    if($konkurs_holati == "off"){
        sms($cid, "<b>Hozirda faol konkurslar mavjud emas.</b>", null);
        exit();
    }

    $odam_soni = $odam ?? 0;
    $referal_havola = "https://t.me/$bot?start=$cid";

    $konkurs_matni = file_get_contents("matn/konkurs.txt");
    if(empty($konkurs_matni)){
        $konkurs_matni = "<b>🏆 Konkursda ishtirok eting!</b>\n\nDo'stlaringizni taklif qiling va sovg'alarga ega bo'ling!\n\nSizning takliflaringiz soni: <b>%odam</b> ta\n\nSizning havolangiz: %havola";
    }

    $matn = str_replace(['%odam', '%havola'], [$odam_soni, "<code>$referal_havola</code>"], $konkurs_matni);

    sms($cid, $matn, json_encode([
        'inline_keyboard' => [
            [['text' => "📊 Top 10 reyting", 'callback_data' => "konkurs_top"]]
        ]
    ]));
}

if($data == "konkurs_top"){
    if(joinchat($cid2) == false) exit();
    $top_users_query = mysqli_query($connect, "SELECT k.user_id, k.odam, u.sana FROM kabinet k JOIN user_id u ON k.user_id = u.user_id WHERE k.odam > 0 ORDER BY k.odam DESC, k.user_id ASC LIMIT 10");

    if(mysqli_num_rows($top_users_query) > 0){
        $reyting_text = "<b>🏆 Konkursning top 10 ishtirokchilari:</b>\n\n";
        $i = 1;
        while($row = mysqli_fetch_assoc($top_users_query)){
            $user_id = $row['user_id'];
            $odam_soni = $row['odam'];
            $sana = $row['sana'];
            $reyting_text .= "<b>$i.</b> <a href='tg://user?id=$user_id'>Foydalanuvchi</a> - <b>$odam_soni</b> ta taklif (Ro'yxatdan o'tgan: $sana)\n";
            $i++;
        }
    } else {
        $reyting_text = "<b>Hali hech kim referal yig'magan. Birinchi bo'ling!</b>";
    }

    del();
    sms($cid2, $reyting_text, $menyu);
}

// =================================================================
// MANHWA MODULINI ISHGA TUSHIRISH
// =================================================================
$manhwaManager = new ManhwaManager($connect, $admin);
if ($manhwaManager->handleUpdate($nurillayev)) {
    exit(); // Agar ManhwaManager xabarni qayta ishlagan bo'lsa, skriptni to'xtatish
}

