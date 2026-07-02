<?php
/**
 * ZedGuard yapılandırma şablonu.
 * Bu dosyayı "config.php" olarak kopyalayıp kendi bilgilerinizi girin.
 * config.php asla git'e eklenmemeli (.gitignore içinde zaten hariç tutuldu).
 */

return [
    // Telegram bildirimleri için: @BotFather'a "/newbot" yazarak token alın.
    // Chat ID'nizi öğrenmek için: bota bir mesaj atın, sonra
    // https://api.telegram.org/bot<TOKEN>/getUpdates adresine gidin.
    'telegram_token'   => 'BURAYA_BOT_TOKEN',
    'telegram_chat_id' => 'BURAYA_CHAT_ID',

    // Hostinger / cPanel hesabınızdaki tüm sitelerin bulunduğu kök dizin.
    // Genelde: /home/<hesap_kullanici_adi>/domains
    'sites_base_dir' => '/home/KULLANICI_ADINIZ/domains',

    // Taramadan hariç tutulacak klasör isimleri (kullanıcı yüklemeleri,
    // önbellek vb. — bunlar sürekli değiştiği için "değişiklik" sayılmamalı).
    'exclude_dirs' => ['uploads', 'upload', 'upload_file', 'media', 'avatars', 'backups', 'cache', 'node_modules', '.git'],

    // true: her calismada (degisiklik olmasa bile) "temiz" bildirimi at
    //       (sessizlik sizi rahatsiz ediyorsa, her tetiklemede haber almak icin)
    // false: sadece asagidaki clean_report_hours saatlerinde bildirim at
    'notify_on_clean' => true,

    // notify_on_clean = false ise, gunun hangi saatlerinde (degisiklik yoksa)
    // "temiz" ozet bildirimi atilsin.
    'clean_report_hours' => [0, 6, 12, 18],

    // true: supheli bulunan dosyalardaki domain'leri T.C. Siber Guvenlik
    // Baskanligi (USOM) tehdit istihbarati API'sine sorar (auth gerekmez,
    // ucretsiz, https://siberguvenlik.gov.tr/api/). Zararli olarak
    // kayitliysa bildirime ekler. API yanit vermezse sessizce atlanir.
    'usom_check_enabled' => true,
];
