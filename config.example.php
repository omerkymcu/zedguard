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

    // Günün hangi saatlerinde (değişiklik yoksa) "temiz" özet bildirimi atılsın.
    'clean_report_hours' => [0, 6, 12, 18],
];
