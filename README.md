# Turkpin API Entegrasyon Projesi

<p align="center">
  <img src="https://assets.turkpin.com/logo/dark.webp" alt="Turkpin Logo" width="200"/>
</p>

<p align="center">
  <strong>Turkpin Bayi API Entegrasyon Test Projesi</strong><br>
  Yazılım Geliştirici İşe Alım Süreci
</p>

---

## Turkpin Hakkında

Turkpin; e-pin, hediye kartı ve dijital ürün satışları konusunda uzmanlaşmış bir dijital ticaret şirketidir.

Kendi e-ticaret platformlarımızı, ödeme altyapılarımızı, API entegrasyonlarımızı ve kimlik doğrulama teknolojilerimizi geliştiriyor ve işletiyoruz.

### Geliştirdiğimiz Çözümler

* **Dijital ürün ve e-pin altyapıları:** Dijital ürünlerin yüksek hacimde yönetilmesi ve teslim edilmesi için uçtan uca sistemler
* **Hediye kartı ve oyun entegrasyonları:** Global oyun ve eğlence platformları için katalog ve ürün teslimat altyapıları
* **B2B API çözümleri:** İş ortaklarının Turkpin hizmetlerini kendi sistemlerine entegre etmesini sağlayan API'ler
* **Ödeme ve sipariş yönetimi:** Ödeme işlemleri, sipariş yaşam döngüsü ve mutabakat sistemleri
* **Kimlik doğrulama ve sahtecilik önleme:** Kullanıcı ve platform güvenliğini destekleyen doğrulama ve risk kontrol sistemleri
* **Bulut tabanlı yazılım platformları:** Yüksek erişilebilirlik ve ölçeklenebilirlik için geliştirilen bulut tabanlı servisler

## Projenin Amacı

Bu proje ile adayın aşağıdaki konulardaki yetkinlikleri değerlendirilecektir:

* Mevcut bir projeyi analiz etme
* Harici API entegrasyonu geliştirme
* PHP ve JavaScript ile uygulama geliştirme
* Hata senaryolarını yönetme
* Kullanıcı deneyimini iyileştirme
* Temiz, güvenli ve sürdürülebilir kod yazma

Bu kapsamda, mevcut web uygulamasına Turkpin Bayi API'sinin entegre edilmesi beklenmektedir.

## Test Projesinin Teknoloji Yapısı

* **Backend:** PHP 8.x
* **Bağımlılık Yönetimi:** Composer
* **Autoloading:** PSR-4
* **Template Engine:** Smarty
* **Frontend:** Bootstrap 5 ve Vanilla JavaScript
* **Routing:** Bramus Router
* **Dil Desteği:** Türkçe ve İngilizce

Turkpin projelerinde genel olarak PHP, JavaScript, TypeScript, React, Next.js, MySQL, Redis, Docker, Google Cloud ve REST API teknolojileri kullanılmaktadır. Ancak bu test projesinde mevcut teknoloji yapısının korunması beklenmektedir.

## Kurulum

### 1. Repository'yi Forklayın

Bu repository'yi kendi GitHub hesabınıza fork edin ve ardından bilgisayarınıza klonlayın.

```bash
git clone https://github.com/kullaniciadi/interview.git
cd interview
```

### 2. Bağımlılıkları Yükleyin

```bash
composer install
```

### 3. Web Server Yapılandırmasını Yapın

* Document root ayarını projenin giriş noktasına göre yapılandırın.
* PHP'nin çalıştığından emin olun.
* Apache kullanıyorsanız `mod_rewrite` modülünü aktif edin.

### 4. API Erişimi Talep Edin

Test API'sine erişebilmek için API isteklerini göndereceğiniz internet bağlantısının genel IP adresini aşağıdaki adrese gönderin:

`integration@turkpin.com`

Whitelist işlemi tamamlandıktan sonra test API'sini kullanabilirsiniz.

## Uygulama ve Geliştirme Notları

Bu repository'de Turkpin Bayi API entegrasyonu mevcut proje yapısı korunarak geliştirilmiştir.

### Ortam Değişkenleri

Proje kök dizinindeki `.env.example` dosyasını `.env` olarak kopyalayın:

```bash
cp .env.example .env
```

Windows PowerShell için:

```powershell
Copy-Item .env.example .env
```

Ardından Turkpin API bilgilerini `.env` dosyasına ekleyin:

```env
TURKPIN_API_URL=https://www.turkpin.net/api.php
TURKPIN_API_USERNAME=
TURKPIN_API_PASSWORD=
TURKPIN_ORDER_ENABLED=false
```

`TURKPIN_ORDER_ENABLED` varsayılan olarak `false` değerindedir. Bu sayede geliştirme ve test sırasında yanlışlıkla gerçek sipariş oluşturulması engellenir.

### Test ve Kod Kontrolleri

Projede unit testler, statik analiz ve kod formatlama kontrolleri bulunmaktadır.

```bash
composer test
composer analyse
composer format-check
```

Gerçek Turkpin API'sine bağlanan integration testleri varsayılan test akışında çalışmaz. Gerekli ortam değişkenleri tanımlandıktan sonra ayrıca çalıştırılabilir.

### Docker ile Çalıştırma

Proje PHP 8.1 ortamında çalışacak şekilde Docker desteğine sahiptir.

Docker image oluşturmak için:

```bash
docker build -t turkpin-interview:php81 .
```

Uygulamayı `.env` dosyasındaki ortam değişkenleriyle çalıştırmak için:

```bash
docker run --rm -p 8080:8080 --env-file .env turkpin-interview:php81
```

Uygulamaya tarayıcı üzerinden aşağıdaki adresten erişilebilir:

```text
http://localhost:8080
```

Docker ortamı aynı zamanda projenin minimum PHP sürümü olan PHP 8.1 üzerinde test, statik analiz ve kod formatı kontrollerini çalıştırmak için kullanılabilir:

```bash
docker run --rm turkpin-interview:php81 composer test
docker run --rm turkpin-interview:php81 composer analyse
docker run --rm turkpin-interview:php81 composer format-check
```

Gerçek Turkpin API'sine bağlanan integration testleri normal test akışından ayrı tutulmuştur. API erişimi ve IP whitelist işlemi tamamlandıktan sonra aşağıdaki şekilde çalıştırılabilir:

```bash
docker run --rm \
  --env-file .env \
  -e TURKPIN_RUN_INTEGRATION_TESTS=true \
  turkpin-interview:php81 \
  vendor/bin/phpunit tests/Integration/TurkpinApiClientIntegrationTest.php
```

Integration testleri katalog verilerini okumaya yöneliktir ve canlı sipariş oluşturmaz.

### Doğrulama Durumu

Proje hem geliştirme ortamında hem de desteklenen minimum PHP sürümü olan PHP 8.1 üzerinde doğrulanmıştır.

Mevcut doğrulama kapsamı:

* Normal test paketi: **75 test, 112 assertion**; canlı API erişimi gerektiren **2 integration test varsayılan olarak skip edilir**.
* PHPStan: **Level 10**, hata bulunmamaktadır.
* PHP-CS-Fixer: format kontrolü temizdir.
* Docker ortamı: **PHP 8.1.34** üzerinde test, statik analiz ve format kontrolleri başarılıdır.
* GitHub Actions: `push` ve `pull_request` akışlarında PHP 8.1 kalite kontrolleri çalıştırılmaktadır.
* Whitelist edilmiş bağlantı üzerinden Turkpin API ile read-only canlı integration doğrulaması yapılmıştır.
* Turkpin test environment üzerinde kontrollü tek bir write E2E siparişi başarıyla çalıştırılmıştır. Normal ürün için adet 1 siparişinde backend doğrulamaları, `epinSiparisYarat` çağrısı, response parsing, sipariş numarası, tutar ve test E-Pin bilgisinin gösterimi ile POST/Redirect/GET sonrası başarı modalı uçtan uca doğrulanmıştır.
* Uygulama Docker üzerinden tarayıcıda çalıştırılarak oyun seçimi, canlı ürün kataloğu ve responsive arayüz akışı smoke test ile kontrol edilmiştir.

Canlı katalog verileri uygulamada hard-code edilmez. Test sırasında Turkpin API'deki ürün sayısı ve stok değerlerinin değiştiği gözlemlenmiş, uygulamanın güncel API verisini herhangi bir kod değişikliğine ihtiyaç duymadan doğru şekilde yansıttığı doğrulanmıştır.

### Bilinen Sınırlamalar

* Tek kullanımlık sipariş tokeni gerçek veya dağıtık idempotency sağlamaz; koruma mevcut PHP session'ı kapsamındaki replay / duplicate-submit senaryolarına yöneliktir.
* Write E2E doğrulaması yalnızca Turkpin tarafından IP whitelist'ine eklenmiş test environment üzerinde kontrollü sipariş kapsamında sipariş testleri yapılmıştır.
* Sipariş isteğine otomatik retry uygulanmaz. Timeout veya bağlantı kopması durumunda isteğin Turkpin tarafında işlenip işlenmediği kesin olarak bilinmeyebileceği için otomatik tekrar ikinci sipariş riski oluşturabilir.
* Session cookie için `Secure` flag'i doğrudan mevcut HTTPS bağlantısına göre belirlenir. Uygulama ileride trusted reverse proxy veya load balancer arkasında çalıştırılırsa proxy-aware HTTPS tespiti ayrıca yapılandırılmalıdır.
* Dockerfile geliştirme, test ve değerlendirme amacıyla hazırlanmıştır; production container hardening, process manager ve deployment altyapısı bu çalışmanın kapsamı dışındadır.

### Teknik Kararlar ve Kullanılan Araçlar

API istekleri ile API cevaplarının işlenmesi ayrı sorumluluklarda tutulmuştur. `TurkpinApiClient` HTTP iletişimini gerçekleştirirken, `TurkpinResponseParser` API'den dönen XML cevaplarının doğrulanması ve uygulamanın kullanacağı veri yapısına dönüştürülmesinden sorumludur.

Sipariş sırasında tarayıcıdan gönderilen ürün bilgilerine doğrudan güvenilmez. Seçilen oyun ve ürün bilgileri sipariş oluşturulmadan önce API üzerinden tekrar alınır ve miktar, stok, ön sipariş ve barem kuralları backend tarafında doğrulanır.

Aynı sipariş formunun aynı session içerisinde tekrar gönderilmesini engellemek için server-side tek kullanımlık sipariş tokeni ve POST/Redirect/GET akışı kullanılmıştır. Token, sipariş API çağrısından önce tüketilir; böylece aynı form tokeninin yeniden kullanılması backend tarafında reddedilir. Frontend tarafında sipariş butonlarının form gönderiminden sonra devre dışı bırakılması yalnızca ek bir kullanıcı deneyimi önlemidir; güvenlik kontrolü backend tarafındadır.

Bu mekanizma gerçek veya dağıtık bir idempotency garantisi değildir. Koruma session kapsamlı bir replay / duplicate-submit önlemidir. Farklı session'lardan, cihazlardan veya birden fazla application instance üzerinden aynı mantıksal siparişin gönderilmesini tek başına engellemez. Gerçek idempotency için Turkpin API tarafından desteklenen benzersiz bir idempotency key veya uygulama tarafında paylaşımlı ve kalıcı bir sipariş anahtarı / kayıt mekanizması gerekir.

Sipariş oluşturma isteğinde otomatik retry uygulanmamıştır. İstek Turkpin tarafında işlenmiş ancak cevap alınamamışsa aynı isteğin otomatik tekrar gönderilmesi ikinci bir sipariş oluşturma riski taşıyabilir.

API ve ağ kaynaklı teknik hatalar kullanıcıya doğrudan gösterilmez. Teknik detaylar Monolog ile loglanırken kullanıcıya Türkçe veya İngilizce genel hata mesajı gösterilir.

XML cevapları işlenirken harici ağ erişimini engellemek amacıyla `LIBXML_NONET` kullanılmıştır.

Projede ek olarak aşağıdaki araçlar kullanılmaktadır:

* **PHPUnit:** Sipariş doğrulama, token yönetimi, API istemcisi ve response parser için unit testler; ayrıca isteğe bağlı gerçek API integration testleri.
* **PHPStan:** Statik analiz için Level 10 seviyesinde kullanılır.
* **PHP-CS-Fixer:** PSR-12 kod formatı kontrolü için kullanılır.
* **Monolog:** API ve uygulama seviyesindeki teknik hataların loglanması için kullanılır.

## API Bilgileri

* **API URL:** `https://www.turkpin.net/api.php`
* **Dokümantasyon:** https://dev.turkpin.com

Test kullanıcı bilgileri dökümantasyonda yer almaktadır. Bu bilgileri kullanarak API isteklerinizi gerçekleştirebilirsiniz.

> API kullanıcı bilgilerini kaynak kod içerisine, frontend tarafına veya commit geçmişine eklemeyin. Ortam değişkenleri veya uygun bir yapılandırma yöntemi kullanın.

## Görevler

### 1. Oyun Listesi

* Oyun listesini Turkpin API üzerinden alın.
* Ana sayfadaki seçim alanında gösterin.
* Başlangıçta herhangi bir oyun seçili olmamalıdır.
* Varsayılan seçenek olarak `Oyun Seçiniz` veya benzeri bir ifade kullanılmalıdır.

### 2. Ürün Listesi

* Kullanıcı bir oyun seçtiğinde ilgili ürünleri API üzerinden alın.
* Ürünleri mevcut tablo yapısında gösterin.
* Oyun seçilmediğinde ürün listesi görünmemelidir.
* Oyun değiştirildiğinde ürün listesi yeni seçime göre güncellenmelidir.

### 3. Sipariş Sistemi

* Kullanıcıdan sipariş için gerekli bilgileri alın.
* Form doğrulaması uygulayın.
* Siparişi backend üzerinden Turkpin API'sine gönderin.
* Aynı siparişin yanlışlıkla birden fazla kez gönderilmesini önleyin.

### 4. Sonuç Gösterimi

* Sipariş sonucunu modal dialog ile gösterin.
* Başarılı ve başarısız sonuçları farklı stillerle sunun.
* Mevcut sipariş detaylarını kullanıcıya gösterin.
* Hata durumlarında anlaşılır mesajlar kullanın.

## Teknik Gereksinimler

* PHP OOP yaklaşımı kullanın.
* Mevcut proje yapısını mümkün olduğunca koruyun.
* Responsive tasarımı bozmayın.
* Türkçe ve İngilizce dil desteğini koruyun.
* İstemci ve sunucu tarafında doğrulama yapın.
* API ve ağ hatalarını yönetin.
* Hassas bilgileri kaynak kod içerisinde saklamayın.
* Temiz ve anlaşılır kod yazın.
* Eklediğiniz bağımlılıkların kullanım amacını açıklayabilin.

## Değerlendirme Kriterleri

### Temel Kriterler

* İstenen özelliklerin çalışması
* API entegrasyonunun doğru yapılması
* Kullanıcı akışının anlaşılır olması
* Hata senaryolarının ele alınması

### Kod Kalitesi

* Temiz ve okunabilir kod
* Uygun sınıf ve servis yapısı
* Güvenli veri işleme
* Gereksiz API çağrılarının önlenmesi
* Anlamlı commit geçmişi

### Artı Değer Sağlayan Çalışmalar

Aşağıdaki çalışmalar zorunlu değildir ancak değerlendirmeye olumlu katkı sağlar:

* Mevcut projedeki sorunların tespit edilmesi ve düzeltilmesi
* Docker veya kurulum scriptleri
* Unit veya integration testleri
* Loglama
* Statik analiz veya kod formatlama araçları
* Teknik kararların kısa şekilde dokümante edilmesi

> Projede bazı teknik eksiklikler ve iyileştirme alanları bulunabilir. Bunların tespit edilmesi ve doğru şekilde çözülmesi değerlendirmeye olumlu katkı sağlar.

## Yapay Zekâ Kullanımı

Yapay zekâ araçlarını aşağıdaki amaçlarla kullanabilirsiniz:

* Dokümantasyon araştırması
* Hata mesajlarını yorumlama
* Teknik konularda bilgi edinme
* Alternatif çözüm yaklaşımlarını değerlendirme
* Yazdığınız kodu gözden geçirme

Ancak projenin tamamının veya önemli bir bölümünün doğrudan yapay zekâya yazdırılması beklenmemektedir.

Teslim ettiğiniz kodun:

* Çalışma mantığını anlayabilmeniz
* Teknik kararlarını açıklayabilmeniz
* Hatalarını tespit edebilmeniz
* Gerektiğinde değiştirebilmeniz

beklenmektedir.

Açıklayamadığınız veya çalışma mantığına hâkim olmadığınız kodlar değerlendirmeyi olumsuz etkileyebilir.

## Commit Standartları

Commit mesajlarının kısa, açık ve yapılan değişikliği anlatır nitelikte olması beklenmektedir.

Tercih edilen format:

```text
(type) scope: description
```

Scope gerekmiyorsa:

```text
(type) description
```

Type kullanılmayacaksa:

```text
scope: description
```

### Kullanılabilecek Türler

* `feat`: Yeni özellik
* `fix`: Hata düzeltmesi
* `docs`: Dokümantasyon değişikliği
* `style`: Kod davranışını değiştirmeyen biçimlendirme
* `refactor`: Kod yapısının düzenlenmesi
* `perf`: Performans iyileştirmesi
* `test`: Test ekleme veya güncelleme
* `build`: Build veya bağımlılık değişiklikleri
* `ci`: CI/CD değişiklikleri
* `chore`: Genel bakım ve yapılandırma işleri

### Örnekler

```text
(feat) api: add game list integration
```

```text
(fix) order: prevent duplicate submissions
```

```text
(docs) readme: add setup instructions
```

```text
(refactor) api: extract client service
```

```text
(test) order: add validation tests
```

```text
api: handle invalid credentials
```

Aşağıdaki gibi belirsiz commit mesajlarından kaçının:

```text
update
fix
changes
final
```

## Kaynaklar

### Teknik Dokümantasyon
- [Turkpin API Dokümantasyonu](https://dev.turkpin.com)
- [PHP Best Practices](https://www.php-fig.org/)
- [Smarty Template Engine](https://www.smarty.net/docs/)
- [Bootstrap 5 Dokümantasyonu](https://getbootstrap.com/docs/5.3/)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [How to Write a Git Commit Message](https://cbea.ms/git-commit/)

### Yararlı Linkler
- [Composer Dokümantasyonu](https://getcomposer.org/doc/)
- [PSR Standards](https://www.php-fig.org/psr/)
- [HTTP Status Codes](https://httpstatuses.com/)

## Sık Sorulan Sorular

**Projeyi tamamlayamazsam gönderebilir miyim?**
Evet. Tamamladığınız bölümleri ve eksik kalan noktaları belirterek teslim edebilirsiniz.

**Pull request açmak zorunlu mu?**
Hayır. Repository bağlantısını paylaşmanız yeterlidir.

**Private repository kullanabilir miyim?**
Evet. Ancak değerlendirme yapacak kişilere erişim vermeniz gerekir.

**Ek kütüphane kullanabilir miyim?**
Evet. Neden kullandığınızı açıklayabilmeniz beklenmektedir.

**Docker veya test yazmak zorunlu mu?**
Hayır. Ancak değerlendirmeye olumlu katkı sağlar.

**Mevcut yapıyı değiştirebilir miyim?**
Gerekli iyileştirmeleri yapabilirsiniz. Ancak projeyi tamamen farklı bir teknoloji veya framework ile yeniden yazmanız beklenmemektedir.

**Yapay zekâ kullanabilir miyim?**
Araştırma, hata analizi ve kod inceleme amacıyla kullanabilirsiniz. Ancak teslim ettiğiniz kodun tamamına hâkim olmanız beklenmektedir.

## Süreç ve Teslim

### Zamanlama

* **Teslim Süresi:** Davet e-postasının gönderilmesinden itibaren en fazla 5 iş günü
* **Değerlendirme Süresi:** Teslimden sonra 2-3 iş günü
* **Geri Bildirim:** Değerlendirme tamamlandıktan sonra mümkün olan en kısa sürede

Projenin tüm gereksinimlerinin tamamlanması tercih edilir ancak zorunlu değildir. Belirtilen süre içerisinde tüm görevleri tamamlayamazsanız, tamamladığınız bölümleri mevcut hâliyle teslim edebilirsiniz. Değerlendirme, gerçekleştirdiğiniz çalışmalar üzerinden yapılacaktır.

### Teslim Süreci

2. **Geliştirme:** Çalışmalarınızı kendi repositoryniz üzerinde gerçekleştirin.
3. **Commit:** Değişikliklerinizi anlamlı commit mesajlarıyla kaydedin.
5. **Bildirim:** Repository bağlantısını `oktay@turkpin.com` adresine gönderin.

### Teslim E-postasında Bulunması Gerekenler

* Adınız ve soyadınız
* Repository bağlantısı
* Tamamladığınız bölümlerin kısa özeti
* Tamamlayamadığınız bölümler
* Bilinen hata veya eksiklikler
* Varsa ek kurulum adımları
* Yapay zekâ araçlarını kullandıysanız hangi amaçlarla kullandığınız

## İletişim ve Destek

### Teknik Destek
- **E-posta**: `integration@turkpin.com`
- **Yanıt Süresi**: 1-2 iş günü
- **Destek Saatleri**: Hafta içi 09:00 - 18:00

### Sorularınız için
- Teknik sorularınızı detaylı şekilde belirtin
- Hata mesajları varsa tam metni paylaşın
- Geliştirme ortamı bilgilerinizi ekleyin

---

<p align="center">
  <strong>Bu proje Turkpin yazılım geliştirici işe alım sürecinin bir parçasıdır.</strong><br>
  <em>Başarılar dileriz!</em>
</p>

<p align="center">
  <sub>© 2026 Turkpin. Tüm hakları saklıdır.</sub>
</p>
