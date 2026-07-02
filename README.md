<div align="center">

# 🛡️ ZedGuard

**Paylaşımlı hosting için tek dosyalık, bağımlılıksız dosya bütünlüğü + malware izleyici.**

Cron ile periyodik çalışır, şüpheli değişiklikleri **Telegram**'a bildirir, bilinen zararlıları **otomatik siler**.

![Status](https://img.shields.io/badge/status-alpha-orange)
![License](https://img.shields.io/badge/license-MIT-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![Dependencies](https://img.shields.io/badge/dependencies-0-brightgreen)

</div>

---

## 🐦 Neden bu araç ortaya çıktı

27 domainlik bir Hostinger hesabındaki tüm siteler, **tek bir sızmış kimlik bilgisi** üzerinden aynı saniyede saldırıya uğradı. Saldırgan (rumuzu "Zedd") her siteye aynı backdoor'u (`awp-niin.php` — uzaktan kod çeken bir dropper), sahte bir WordPress eklentisi (`wp-content/plugins/lexom/` — "Kicau Mania Plugin" adıyla SEO spam enjekte ediyordu) ve `wp-config.php`'lere "`/** Secured by Zedd **/`" imzalı bir enjeksiyon bıraktı.

Sorun şuydu: **bunu haftalarca fark etmedik.** Paylaşımlı hosting'te ne bir WAF, ne bir dosya bütünlük izleyicisi vardı. Sistem yeniden kurulup şifreler rotasyona sokulduktan **saatler sonra bile** aynı saldırı bir kez daha, aynı anda tüm sitelere düştü — çünkü hiçbir şey bunu **anında** haber vermiyordu.

ZedGuard, bunun bir daha yaşanmaması için bir gecede yazıldı. Basit tutuldu: **tek dosya, sıfır bağımlılık, sadece PHP + cron.**

## ⚠️ Alpha uyarısı

Bu araç şu anda **gerçek bir saldırı sonrası, gerçek bir hesapta** kullanılıyor ve işe yarıyor — ama:
- Tek bir ortamda (Hostinger paylaşımlı hosting) test edildi
- Bilinen imza listesi şu anki olayın IOC'lerine dayanıyor, genel bir malware veritabanı değil
- Kod tabanı küçük ve gözden geçirilmeye açık — PR'lar ve issue'lar memnuniyetle karşılanır

**Bunu tam bir antivirüs/güvenlik duvarının yerine koymayın.** WordPress siteleriniz için ek olarak [Wordfence](https://wordfence.com) gibi olgun bir çözüm kullanmanızı öneririz — ZedGuard onun tamamlayıcısı, yerine geçeni değil.

## ✨ Ne yapıyor

```
┌─────────────────────────────────────────────────────────┐
│  Hostinger cron (saatlik)                                │
│         │                                                 │
│         ▼                                                 │
│  monitor.php  ──►  /home/kullanici/domains/*/public_html  │
│         │              (her sitenin kökü, uploads hariç)  │
│         ▼                                                 │
│  ┌───────────────────────────────────────────────────┐   │
│  │ Katman 1: Bilinen imza eşleşmesi                   │   │
│  │   → OTOMATİK SİL + Telegram bildirim               │   │
│  ├───────────────────────────────────────────────────┤   │
│  │ Katman 2: Genel şüpheli davranış (eval/base64,     │   │
│  │   shell_exec($_GET), unicode obfuscation, vb.)     │   │
│  │   → SADECE BİLDİR, silmez                          │   │
│  ├───────────────────────────────────────────────────┤   │
│  │ Katman 3: Dosya ekleme/silme/boyut değişikliği     │   │
│  │   → BİLDİR                                         │   │
│  └───────────────────────────────────────────────────┘   │
│         │                                                 │
│         ▼                                                 │
│     📱 Telegram bildirimi                                 │
└─────────────────────────────────────────────────────────┘
```

- **Sıfır bağımlılık** — sadece çekirdek PHP (Composer, framework yok)
- **Otomatik site keşfi** — yeni bir domain eklediğinizde elle güncelleme gerekmez, siz sildiğinizde de yanlış alarm vermez
- **Web'den erişilemez** — `.htaccess` ile tamamen kapalı, sadece cron/CLI çalıştırabilir
- **FTP'siz** — sunucunun kendi dosya sistemine doğrudan erişir, ana FTP şifresi değişse bile etkilenmez

## 📱 Örnek bildirimler

```
🛡️ ZedGuard kuruldu. Baseline alındı (11 site). Periyodik taramalar başlıyor.

✅ Tarama yapıldı — 2026-07-02 18:00 — tüm siteler temiz (11 site kontrol edildi)

🗑️ OTOMATİK TEMİZLENDİ (bilinen imza)
mysite.com: awp-niin.php

🔎 ŞÜPHELİ (elle kontrol edin, silinmedi)
mysite.com: wp-content/themes/custom/footer-new.php — obfuscated eval

⚠️ DOSYA DEĞİŞİKLİĞİ — 2026-07-02 19:00
⚠️ mysite.com — DEĞİŞİKLİK TESPİT EDİLDİ
  + YENİ: wp-content/uploads/2026/07/image.jpg
```

## 🚀 Kurulum

### 1. Dosyaları sunucuya yükleyin

Sitelerinizden **birinin** kök dizini altına, **web'den erişilemeyecek** bir klasöre koyun:

```
/home/kullanici/domains/example.com/public_html/_zedguard/
  ├── monitor.php
  ├── config.example.php
  └── .htaccess   (aşağıya bakın)
```

`.htaccess` içeriği (klasörü tamamen kapatır):

```apache
Order Allow,Deny
Deny from all
```

### 2. Yapılandırın

```bash
cp config.example.php config.php
```

`config.php`'yi kendi bilgilerinizle doldurun:

```php
return [
    'telegram_token'   => 'BOT_TOKEN',      // @BotFather'dan
    'telegram_chat_id' => 'CHAT_ID',        // botunuza mesaj atıp getUpdates ile öğrenin
    'sites_base_dir'   => '/home/kullaniciadi/domains',
    'exclude_dirs'     => ['uploads', 'media', 'backups', 'cache', 'node_modules', '.git'],
    'clean_report_hours' => [0, 6, 12, 18], // temiz olduğunda günde kaç kez/ne zaman bildirim
];
```

### 3. Cron job ekleyin

hPanel (veya cPanel) → Cron Jobs:

```
0 * * * *  php /home/kullaniciadi/domains/example.com/public_html/_zedguard/monitor.php
```

Saatlik önerilir; daha sık isterseniz `*/15 * * * *` gibi bir ifade kullanabilirsiniz.

### 4. İlk çalıştırma

İlk çalıştığında sadece baseline alır ve "kuruldu" bildirimi gönderir — henüz karşılaştıracak bir şey olmadığı için alarm vermez. Cron'u tetiklemeden test etmek isterseniz, script'i geçici olarak `.htaccess` korumasız bir yere koyup tarayıcıdan/`curl` ile bir kez çalıştırıp hemen kaldırabilirsiniz.

## 🔧 Kendi imzalarınızı eklemek

Kendi ortamınızda farklı bir saldırı/imza ile karşılaşırsanız, `monitor.php` içindeki listelere ekleyin:

```php
$KNOWN_MALWARE_NAMES = ['awp-niin.php', 'dragonshell.php', /* ... kendi bulgunuz ... */];
$KNOWN_MALWARE_CONTENT = ['myzedd.tech', /* ... kendi bulgunuz ... */];
```

## 🗺️ Yol haritası

- [ ] Yapılandırılabilir bildirim kanalları (Discord, Slack, e-posta)
- [ ] `.env` tabanlı yapılandırma seçeneği
- [ ] Bilinen imza listesi için ayrı, güncellenebilir bir JSON dosyası
- [ ] Web tabanlı basit bir durum paneli (opsiyonel, ayrı `.htaccess` korumalı)
- [ ] Çoklu hesap desteği (birden fazla cPanel/Hostinger hesabını tek Telegram botuna bağlama)

## 🤝 Katkı

Issue ve PR'lar açık — özellikle yeni malware imzaları, farklı hosting ortamlarında test sonuçları ve hata düzeltmeleri için.

## 📄 Lisans

MIT — [LICENSE](LICENSE) dosyasına bakın.

---

<div align="center">
<sub>Gerçek bir saldırıdan doğdu, gerçek bir hesapta çalışıyor. İyi şanslar. 🍀</sub>
</div>
