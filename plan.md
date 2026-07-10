# AiTut — UI/UX İyileştirme Planı

## Faz 1 — Kritik Mantık Hataları

- [x] **F1-1** Onboarding'de native ≠ target validasyonu — aynı dil seçilememeli
- [x] **F1-2** Onboarding sayfası `onboarding_completed` kontrolü yapmalı, tamamlanmışsa dashboard'a yönlendirmeli
- [x] **F1-3** `?page=update_lang` target dili native ile aynı yapmaya izin vermemeli
- [x] **F1-4** Trial expired modal'ındaki "Review Chat" butonu ya işlevsel olsun ya kaldırılsın
- [x] **F1-5** Zaten ödeme yapmış kullanıcı pricing sayfasında "Zaten abonesiniz" mesajı görmeli, planlar gösterilmemeli
- [x] **F1-6** Dashboard'daki "Upgrade to A2" butonu ya çalışır hale getirilmeli ya kaldırılmalı
- [x] **F1-7** Trial kalan mesaj sayısı client-server senkronizasyonu düzeltilmeli

## Faz 2 — Mobil & Responsive İyileştirmeleri

- [x] **F2-1** Mobil için hamburger menü eklenmeli (navbar linkleri lg altında gizleniyor)
- [x] **F2-2** Chat sidebar'daki dil değiştirici mobil (dokunmatik) uyumlu hale getirilmeli
- [x] **F2-3** Navbar'a dil değiştirme seçeneği eklenmeli (şu an sadece chat sidebar'da var)

## Faz 3 — Chat & Konuşma Deneyimi

- [x] **F3-1** Yeni sohbet başlatınca sidebar'daki konuşma listesi dinamik güncellenmeli
- [x] **F3-2** Konuşma yüklenirken loading/skeleton göstergesi eklenmeli
- [x] **F3-3** Chat input'daki "+" butonu işlevlendirilmeli veya kaldırılmalı
- [x] **F3-4** Sağ paneldeki "Documents" linki (`href="#"`) kaldırılmalı veya işlevlendirilmeli
- [x] **F3-5** Konuşma listesi arama sonucu sıfır eşleşme gösterilmeli

## Faz 4 — Flashcards & Öğrenme Araçları

- [x] **F4-1** Flashcard review'da SM-2'nin 4 kalitesi de sunulmalı (Again/Hard/Good/Easy)
- [x] **F4-2** "Again" oylandığında kullanıcıya görsel geri bildirim eklenmeli
- [x] **F4-3** Kategori filtreleri statik kart verisiyle uyumlu hale getirilmeli

## Faz 5 — Ödeme & Abonelik

- [x] **F5-1** Ödeme sonrası polling'e 30 saniye timeout eklenmeli, aşarsa hata gösterilmeli
- [x] **F5-2** Pricing sayfasında aktif abonelik durumu net gösterilmeli

## Faz 6 — Genel İyileştirmeler

- [x] **F6-1** Giriş hata mesajı daha spesifik hale getirilmeli (email bulunamadı / şifre yanlış)
- [x] **F6-2** Kayıt formuna server-side validasyon eklenmeli (şifre gücü, email formatı)
- [x] **F6-3** Boş/dekoratif alanlar ve ölü linkler temizlenmeli
