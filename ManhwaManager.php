<?php

class ManhwaManager {
    private $connect;
    private $update;
    private $text;
    private $data;
    private $cid;
    private $mid;
    private $uid;
    private $admin_ids;
    private $panel;
    private $boshqarish;

    public function __construct($db_connection, $admin_list) {
        $this->connect = $db_connection;
        $this->admin_ids = $admin_list;
        
        // Boshqaruv tugmalarini sozlash
        $this->panel = json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "🗄 Boshqarish"]], [['text' => "◀️ Orqaga"]]]]);
        $this->boshqarish = json_encode(['resize_keyboard' => true, 'keyboard' => [[['text' => "🗄 Boshqarish"]]]]);
    }

    public function handleUpdate($update) {
        $this->update = $update;
        
        // Foydalanuvchi ma'lumotlarini aniqlash
        if (isset($update->message)) {
            $this->text = $update->message->text;
            $this->cid = $update->message->chat->id;
            $this->mid = $update->message->message_id;
            $this->uid = $update->message->from->id;
        } elseif (isset($update->callback_query)) {
            $this->data = $update->callback_query->data;
            $this->cid = $update->callback_query->message->chat->id;
            $this->mid = $update->callback_query->message->message_id;
            $this->uid = $update->callback_query->from->id;
        } else {
            return false; // Ishlov berilmaydigan update turi
        }

        $step = file_get_contents("step/{$this->cid}.step");

        // Admin buyruqlari
        if (in_array($this->uid, $this->admin_ids)) {
            if ($this->text == "📚 Manhwa Boshqaruvi") return $this->showAdminMenu();
            if ($this->data == "manhwa_admin_menu") return $this->showAdminMenu(true);
            
            // Manhwa/Manga qo'shish
            if ($this->data == "manhwa_add") return $this->startAddManhwa();
            if ($step == "manhwa_add_title") return $this->addManhwaTitle();
            if ($step == "manhwa_add_desc") return $this->addManhwaDesc();
            if ($step == "manhwa_add_author") return $this->addManhwaAuthor();
            if ($step == "manhwa_add_genre") return $this->addManhwaGenre();
            if ($step == "manhwa_add_status" && strpos($this->data, "manhwa_status=") === 0) return $this->addManhwaStatus();
            if ($step == "manhwa_add_cover") return $this->addManhwaCover();

            // Manhwa tahrirlash
            if ($this->data == "manhwa_edit_start") return $this->startEditManhwa();
            if ($step == "manhwa_edit_id") return $this->showEditOptions();
            if (strpos($this->data, "manhwa_edit_field=") === 0) return $this->promptForNewValue();
            if (strpos($step, "manhwa_edit_update=") === 0) return $this->updateManhwaField();
            if (strpos($this->data, "manhwa_edit_status_update=") === 0) return $this->updateManhwaStatus();
            if (strpos($this->data, "manhwa_delete_confirm=") === 0) return $this->deleteManhwa();

            // Eski "Manga qo'shish" logikasi (animelar jadvaliga)
            if ($this->data == "add-manga") return $this->startAddMangaToAnimeTable();
            if ($step == "manga-name") return $this->addMangaName();
            if ($step == "manga-episodes") return $this->addMangaEpisodes();
            if ($step == "manga-country") return $this->addMangaCountry();
            if ($step == "manga-language") return $this->addMangaLanguage();
            if ($step == "manga-year") return $this->addMangaYear();
            if ($step == "manga-genre") return $this->addMangaGenre();
            if ($step == "manga-picture") return $this->addMangaPicture();

            // Qism qo'shish
            if ($this->data == "manhwa_add_chapter") return $this->startAddChapter();
            if ($this->text == "✅ Tugatish" && $step == "manhwa_chapter_add_files") { // Buni birinchi tekshirish kerak
                if (file_exists("step/{$this->cid}.step")) {
                    unlink("step/{$this->cid}.step");
                }
                unlink("step/manhwa_chapter_data_{$this->cid}.txt");
                sms($this->cid, "<b>✅ Qism qo'shish jarayoni yakunlandi.</b>", $this->panel);
                return true;
            }
            if ($step == "manhwa_chapter_add_id") return $this->addChapterId(); // Keyin bularni
            if ($step == "manhwa_chapter_add_files") return $this->addChapterFile(); // Va nihoyat buni
        }
        
        // Manhwa qidirish (barcha foydalanuvchilar uchun)
        if ($this->data == "manhwa_search_by_code") return $this->startSearchByCode();
        if ($step == "manhwa_search_by_code_wait") return $this->processSearchByCode();

        // Foydalanuvchi buyruqlari
        if ($this->text == "📚 Manhwalar" && empty($step)) return $this->showManhwaList();
        if (strpos($this->data, "manhwa_list_page=") === 0) return $this->showManhwaList(true);
        if (strpos($this->data, "manhwa_show_info=") === 0) return $this->showManhwaInfo();
        if (strpos($this->data, "manhwa_chapters=") === 0) return $this->showChapterList();
        if (strpos($this->data, "manhwa_read=") === 0) return $this->readChapter();

        return false; // Agar bu modulga tegishli bo'lmasa
    }

    // =================================================================
    // ADMIN FUNKSIYALARI
    // =================================================================

    private function showAdminMenu($is_edit = false) {
        $text = "<b>📚 Manhwa boshqaruv bo'limi:</b>";
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => "➕ Manhwa qo'shish", 'callback_data' => "manhwa_add"]],
                [['text' => "📝 Tahrirlash", 'callback_data' => "manhwa_edit_start"]],
                [['text' => "🗑 Qismni o'chirish", 'callback_data' => "manhwa_delete_chapter_start"]],
                [['text' => "➕ Qism qo'shish (PDF)", 'callback_data' => "manhwa_add_chapter"]],
                [['text' => "◀️ Orqaga", 'callback_data' => "boshqarish"]]
            ]
        ]);
        if ($is_edit) {
            edit($this->cid, $this->mid, $text, $keyboard);
        } else {
            sms($this->cid, $text, $keyboard);
        }
        return true;
    }

    private function startAddManhwa() {
        del();
        sms($this->cid, "<b>📘 Manhwa nomini kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manhwa_add_title");
        return true;
    }

    private function addManhwaTitle() {
        put("step/manhwa_data_{$this->cid}.txt", json_encode(['title' => $this->text]));
        sms($this->cid, "<b>📝 Manhwa uchun qisqacha tavsif yuboring:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manhwa_add_desc");
        return true;
    }
    
    private function addManhwaDesc() {
        $manhwa_data = json_decode(get("step/manhwa_data_{$this->cid}.txt"), true);
        $manhwa_data['description'] = $this->text;
        put("step/manhwa_data_{$this->cid}.txt", json_encode($manhwa_data));
        sms($this->cid, "<b>✍️ Muallifni kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manhwa_add_author");
        return true;
    }

    private function addManhwaAuthor() {
        $manhwa_data = json_decode(get("step/manhwa_data_{$this->cid}.txt"), true);
        $manhwa_data['author'] = $this->text;
        put("step/manhwa_data_{$this->cid}.txt", json_encode($manhwa_data));
        sms($this->cid, "<b>🎭 Janrlarini kiriting (vergul bilan ajratib):</b>\n\n<i>Namuna: Jangari, Fantastika</i>", $this->boshqarish);
        put("step/{$this->cid}.step", "manhwa_add_genre");
        return true;
    }

    private function addManhwaGenre() {
        $manhwa_data = json_decode(get("step/manhwa_data_{$this->cid}.txt"), true);
        $manhwa_data['genre'] = $this->text;
        put("step/manhwa_data_{$this->cid}.txt", json_encode($manhwa_data));
        sms($this->cid, "<b>📊 Holatini tanlang:</b>", json_encode([
            'inline_keyboard' => [
                [['text' => "▶️ Davom etmoqda", 'callback_data' => "manhwa_status=ongoing"]],
                [['text' => "✅ Tugallangan", 'callback_data' => "manhwa_status=completed"]]
            ]
        ]));
        put("step/{$this->cid}.step", "manhwa_add_status");
        return true;
    }

    private function addManhwaStatus() {
        $status = str_replace("manhwa_status=", "", $this->data);
        $manhwa_data = json_decode(get("step/manhwa_data_{$this->cid}.txt"), true);
        $manhwa_data['status'] = $status;
        put("step/manhwa_data_{$this->cid}.txt", json_encode($manhwa_data));
        del();
        sms($this->cid, "<b>🏞 Manhwa muqovasi uchun rasm yuboring:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manhwa_add_cover");
        return true;
    }

    private function addManhwaCover() {
        if (isset($this->update->message->photo)) {
            $file_id = $this->update->message->photo[count($this->update->message->photo) - 1]->file_id;
            $manhwa_data = json_decode(get("step/manhwa_data_{$this->cid}.txt"), true);

            $title = mysqli_real_escape_string($this->connect, $manhwa_data['title']);
            $description = mysqli_real_escape_string($this->connect, $manhwa_data['description']);
            $author = mysqli_real_escape_string($this->connect, $manhwa_data['author']);
            $genre = mysqli_real_escape_string($this->connect, $manhwa_data['genre']);
            $status = mysqli_real_escape_string($this->connect, $manhwa_data['status']);
            $cover_file_id = mysqli_real_escape_string($this->connect, $file_id);

            $sql = "INSERT INTO `manhwas` (`title`, `description`, `author`, `genre`, `status`, `cover_file_id`) VALUES ('$title', '$description', '$author', '$genre', '$status', '$cover_file_id')";

            if ($this->connect->query($sql)) {
                $new_id = $this->connect->insert_id;
                sms($this->cid, "<b>✅ Manhwa muvaffaqiyatli qo'shildi!</b>\n\n<b>Manhwa ID:</b> <code>$new_id</code>", $this->panel);
            } else {
                sms($this->cid, "<b>⚠️ Xatolik yuz berdi!</b>\n\n<code>" . $this->connect->error . "</code>", $this->panel);
            }

            unlink("step/{$this->cid}.step");
            unlink("step/manhwa_data_{$this->cid}.txt");
        } else {
            sms($this->cid, "<b>❌ Iltimos, faqat rasm yuboring!</b>", $this->boshqarish);
        }
        return true;
    }

    // =================================================================
    // "MANGA" QO'SHISH (ANIMELAR JADVALIGA)
    // =================================================================

    private function startAddMangaToAnimeTable() {
        del();
        sms($this->cid, "<b>📚 Manga nomini kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-name");
        return true;
    }

    private function addMangaName() {
        put("step/manga_add_tmp_1.txt", $this->text);
        sms($this->cid, "<b>📖 Qismlar sonini kiriting:</b>\n\n<i>Namuna: <code>0/??</code> (jami noma'lum bo'lsa) yoki <code>0/150</code> (jami ma'lum bo'lsa)</i>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-episodes");
        return true;
    }

    private function addMangaEpisodes() {
        put("step/manga_add_tmp_2.txt", $this->text);
        sms($this->cid, "<b>🌍 Qaysi davlat ishlab chiqarganini kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-country");
        return true;
    }

    private function addMangaCountry() {
        put("step/manga_add_tmp_3.txt", $this->text);
        sms($this->cid, "<b>🇺🇿 Qaysi tilda ekanligini kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-language");
        return true;
    }

    private function addMangaLanguage() {
        put("step/manga_add_tmp_4.txt", $this->text);
        sms($this->cid, "<b>📆 Qaysi yilda ishlab chiqarilganini kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-year");
        return true;
    }

    private function addMangaYear() {
        put("step/manga_add_tmp_5.txt", $this->text);
        sms($this->cid, "<b>🎞 Janrlarini kiriting:</b>\n\n<i>Na'muna: Drama, Fantastika, Sarguzash</i>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-genre");
        return true;
    }

    private function addMangaGenre() {
        put("step/manga_add_tmp_6.txt", $this->text);
        sms($this->cid, "<b>🏞 Rasmini yoki 240 soniyadan oshmagan video yuboring:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manga-picture");
        return true;
    }

    private function addMangaPicture() {
        if (isset($this->update->message->photo) || isset($this->update->message->video)) {
            $file_id = '';
            if (isset($this->update->message->photo)) {
                $file_id = $this->update->message->photo[count($this->update->message->photo) - 1]->file_id;
            } elseif (isset($this->update->message->video)) {
                if ($this->update->message->video->duration <= 240) {
                    $file_id = $this->update->message->video->file_id;
                } else {
                    sms($this->cid, "<b>⚠️ Video 240 soniyadan oshmasligi kerak!</b>", $this->panel);
                    return true;
                }
            }

            $nom = mysqli_real_escape_string($this->connect, get("step/manga_add_tmp_1.txt"));
            $qismi = mysqli_real_escape_string($this->connect, get("step/manga_add_tmp_2.txt"));
            $davlati = mysqli_real_escape_string($this->connect, get("step/manga_add_tmp_3.txt"));
            $tili = mysqli_real_escape_string($this->connect, get("step/manga_add_tmp_4.txt"));
            $yili = mysqli_real_escape_string($this->connect, get("step/manga_add_tmp_5.txt"));
            $janri = mysqli_real_escape_string($this->connect, get("step/manga_add_tmp_6.txt"));
            $date = date('H:i d.m.Y');

            $sql = "INSERT INTO `animelar` (`nom`, `rams`, `qismi`, `davlat`, `tili`, `yili`, `janri`, `qidiruv`, `type`, `sana`, `aniType`) VALUES ('$nom', '$file_id', '$qismi', '$davlati', '$tili', '$yili', '$janri', '0', 'manga', '$date', 'Manga')";

            if ($this->connect->query($sql)) {
                $code = $this->connect->insert_id;
                sms($this->cid, "<b>✅ Manga qo'shildi!</b>\n\n<b>Manga kodi:</b> <code>$code</code>", $this->panel);
            } else {
                sms($this->cid, "<b>⚠️ Xatolik!</b>\n\n<code>" . $this->connect->error . "</code>", $this->panel);
            }

            // Vaqtinchalik fayllarni o'chirish
            unlink("step/{$this->cid}.step");
            for ($i = 1; $i <= 6; $i++) {
                unlink("step/manga_add_tmp_{$i}.txt");
            }
        } else {
            sms($this->cid, "<b>⚠️ Iltimos, rasm yoki 240 soniyadan oshmagan video yuboring!</b>", $this->panel);
        }
        return true;
    }

    private function startAddChapter() {
        del();
        sms($this->cid, "<b>📚 PDF qismlar qo'shmoqchi bo'lgan Manhwa ID sini kiriting:</b>", $this->boshqarish);
        put("step/{$this->cid}.step", "manhwa_chapter_add_id");
        return true;
    }

    private function addChapterId() {
        if (is_numeric($this->text)) {
            $manhwa_id = (int)$this->text; //NOSONAR
            $check = mysqli_query($this->connect, "SELECT id, title FROM manhwas WHERE id = $manhwa_id");
            if (mysqli_num_rows($check) > 0) {
                $manhwa = mysqli_fetch_assoc($check);
                put("step/manhwa_chapter_data_{$this->cid}.txt", json_encode(['manhwa_id' => $manhwa_id]));
                sms($this->cid, "<b>Manhwa:</b> {$manhwa['title']}\n\n<b> Endi qismlarni (PDF fayllarni) ketma-ket yuboring.</b>\n\n<i>Jarayonni to'xtatish uchun '✅ Tugatish' tugmasini bosing.</i>", json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => [[['text' => "✅ Tugatish"]]]
                ]));
                put("step/{$this->cid}.step", "manhwa_chapter_add_files");
            } else {
                sms($this->cid, "<b>❌ Ushbu ID ga ega manhwa topilmadi.</b>", $this->boshqarish);
            }
        } else {
            sms($this->cid, "<b>❌ ID faqat raqamlardan iborat bo'lishi kerak.</b>", $this->boshqarish);
        }
        return true;
    }

    private function addChapterFile() {
        if (isset($this->update->message->document) && $this->update->message->document->mime_type == 'application/pdf') {
            $file_id = $this->update->message->document->file_id;
            $chapter_data = json_decode(get("step/manhwa_chapter_data_{$this->cid}.txt"), true);
            $manhwa_id = $chapter_data['manhwa_id'];

            // Keyingi qism raqamini avtomatik aniqlash
            $last_chapter_res = mysqli_query($this->connect, "SELECT MAX(CAST(chapter_number AS DECIMAL(10,2))) as max_chapter FROM chapters WHERE manhwa_id = $manhwa_id");
            $last_chapter_row = mysqli_fetch_assoc($last_chapter_res);
            $next_chapter_number = ($last_chapter_row['max_chapter'] !== null) ? $last_chapter_row['max_chapter'] + 1 : 1;

            $safe_file_id = mysqli_real_escape_string($this->connect, $file_id);
            $sql = "INSERT INTO `chapters` (`manhwa_id`, `chapter_number`, `file_id`, `type`) VALUES ($manhwa_id, '$next_chapter_number', '$safe_file_id', 'document')";

            if ($this->connect->query($sql)) {
                sms($this->cid, "<b>✅ `$manhwa_id` ID'li manhwaga `$next_chapter_number`-qism (PDF) yuklandi. Keyingisini yuboring...</b>", null);
            } else {
                sms($this->cid, "<b>⚠️ Xatolik yuz berdi!</b>\n\n<code>" . $this->connect->error . "</code>", $this->panel);
                unlink("step/{$this->cid}.step"); // Xatolik bo'lsa jarayonni to'xtatish
            }
        } else {
            sms($this->cid, "<b>❌ Iltimos, faqat PDF fayl yuboring yoki '✅ Tugatish' tugmasini bosing.</b>");
        }
        return true;
    }

    // =================================================================
    // FOYDALANUVCHI FUNKSIYALARI
    // =================================================================

    private function showManhwaList($is_edit = false) {
        if(joinchat($this->cid) == false) return true;

        $page = 1;
        if ($is_edit) {
            $page = (int)str_replace("manhwa_list_page=", "", $this->data);
        }
        
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $count_query = mysqli_query($this->connect, "SELECT COUNT(*) as total FROM manhwas");
        $total_items = mysqli_fetch_assoc($count_query)['total'];
        $total_pages = ceil($total_items / $limit);

        if ($total_items == 0) {
            $text = "Hozircha manhwalar mavjud emas.";
            if ($is_edit) edit($this->cid, $this->mid, $text, null);
            else sms($this->cid, $text, null);
            return true;
        }

        $result = mysqli_query($this->connect, "SELECT id, title FROM manhwas ORDER BY added_at DESC LIMIT $limit OFFSET $offset");
        $buttons = [];
        $text_response = "<b>📚 Mavjud manhwalar ro'yxati:</b>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            $buttons[] = [['text' => $row['title'], 'callback_data' => "manhwa_show_info={$row['id']}"]];
        }

        $pagination_buttons = [];
        if ($page > 1) $pagination_buttons[] = ['text' => "⬅️", 'callback_data' => "manhwa_list_page=" . ($page - 1)];
        if ($total_pages > 1) $pagination_buttons[] = ['text' => "$page/$total_pages", 'callback_data' => "null"];
        if ($page < $total_pages) $pagination_buttons[] = ['text' => "➡️", 'callback_data' => "manhwa_list_page=" . ($page + 1)];
        
        if (!empty($pagination_buttons)) $buttons[] = $pagination_buttons;
        $buttons[] = [['text' => "🔍 Kod orqali qidirish", 'callback_data' => "manhwa_search_by_code"]];

        if ($is_edit) {
            edit($this->cid, $this->mid, $text_response, json_encode(['inline_keyboard' => $buttons]));
        } else {
            sms($this->cid, $text_response, json_encode(['inline_keyboard' => $buttons]));
        }
        return true;
    }

    private function showManhwaInfo($manhwa_id = null) {
        if ($manhwa_id === null) {
            del();
            $manhwa_id = (int)str_replace("manhwa_show_info=", "", $this->data);
        }
        if(joinchat($this->cid, "manhwa_show_info=$manhwa_id") == false) return true;
        $query = mysqli_query($this->connect, "SELECT * FROM manhwas WHERE id = $manhwa_id");
        
        if(mysqli_num_rows($query) > 0) {
            $manhwa = mysqli_fetch_assoc($query);
            
            $caption = "<b>📘 {$manhwa['title']}</b>\n\n";
            if (!empty($manhwa['chapters_text'])) {
                $caption .= "<b>📖 Qismlar:</b> {$manhwa['chapters_text']}\n";
            }
            $caption .= "<b>✍️ Muallif:</b> {$manhwa['author']}\n";
            $caption .= "<b>🎭 Janr:</b> {$manhwa['genre']}\n";
            $status_text = ($manhwa['status'] == 'completed') ? "✅ Tugallangan" : "▶️ Davom etmoqda";
            $caption .= "<b>📊 Holati:</b> $status_text\n\n";
            $caption .= "<i>{$manhwa['description']}</i>";
            
            $keyboard = [
                [['text' => "📖 Qismlarni o'qish", 'callback_data' => "manhwa_chapters=$manhwa_id"]],
                [['text' => "◀️ Ro'yxatga qaytish", 'callback_data' => "manhwa_list_page=1"]]
            ];
            
            bot('sendPhoto', [
                'chat_id' => $this->cid,
                'photo' => $manhwa['cover_file_id'],
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        } else {
            sms($this->cid, "❌ Manhwa topilmadi.", null);
        }
        return true;
    }

    private function showChapterList() { //NOSONAR
        del();

        $manhwa_id = (int)str_replace("manhwa_chapters=", "", $this->data);
        if(joinchat($this->cid, "manhwa_chapters=$manhwa_id") == false) return true;

        $manhwa_query = mysqli_query($this->connect, "SELECT title FROM manhwas WHERE id = $manhwa_id");
        $manhwa_title = mysqli_fetch_assoc($manhwa_query)['title'];

        $chapters_query = mysqli_query($this->connect, "SELECT id, chapter_number FROM chapters WHERE manhwa_id = $manhwa_id ORDER BY chapter_number ASC");

        if(mysqli_num_rows($chapters_query) > 0) {
            $text = "<b>{$manhwa_title}</b>\n\n📖 O'qish uchun qismni tanlang:";
            $buttons = [];
            while($chapter = mysqli_fetch_assoc($chapters_query)) {
                $buttons[] = ['text' => "{$chapter['chapter_number']}-qism", 'callback_data' => "manhwa_read={$chapter['id']}"];
            }
            $keyboard = array_chunk($buttons, 3);
            $keyboard[] = [['text' => "◀️ Orqaga", 'callback_data' => "manhwa_show_info=$manhwa_id"]];

            sms($this->cid, $text, json_encode(['inline_keyboard' => $keyboard]));
        } else {
            sms($this->cid, "😔 Ushbu manhwa uchun hali qismlar qo'shilmagan.", json_encode([
                'inline_keyboard' => [[['text' => "◀️ Orqaga", 'callback_data' => "manhwa_show_info=$manhwa_id"]]]
            ]));
        }
        return true;
    }

    private function readChapter() { //NOSONAR
        del();

        $chapter_id = (int)str_replace("manhwa_read=", "", $this->data);

        $chapter_query = mysqli_query($this->connect, "SELECT c.*, m.title as manhwa_title FROM chapters c JOIN manhwas m ON c.manhwa_id = m.id WHERE c.id = $chapter_id");
        if(joinchat($this->cid, "manhwa_read=$chapter_id") == false) return true;

        if(mysqli_num_rows($chapter_query) > 0) {
            $chapter = mysqli_fetch_assoc($chapter_query);

            if ($chapter['type'] == 'document' && !empty($chapter['file_id'])) {
                // PDF faylni yuborish
                $caption = "<b>{$chapter['manhwa_title']}</b>\n{$chapter['chapter_number']}-qism";
                $keyboard = json_encode(['inline_keyboard' => [
                    [['text' => '📖 Barcha qismlar', 'callback_data' => "manhwa_chapters={$chapter['manhwa_id']}"]]
                ]]);

                bot('sendDocument', [
                    'chat_id' => $this->cid,
                    'document' => $chapter['file_id'],
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $keyboard
                ]);
            } else {
                // Hali sahifalarga bo'lib o'qish logikasi (agar kerak bo'lsa)
                // Hozircha bu holatda xabar beramiz
                sms($this->cid, "Bu qism sahifalarga bo'lingan, lekin o'qish funksiyasi hali tayyor emas.", json_encode([
                    'inline_keyboard' => [[['text' => '◀️ Orqaga', 'callback_data' => "manhwa_chapters={$chapter['manhwa_id']}"]]]
                ]));
            }
        } else {
            accl($this->update->callback_query->id, "❌ Qism topilmadi.", true);
        }
        return true;
    }
}

?>