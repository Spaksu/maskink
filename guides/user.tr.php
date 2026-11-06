<?php // User guide – Turkish content (included by guide.php)
g_open(
    'Kullanım Kılavuzu',
    'Maskink, sizinle yapay zekâ arasında durur: <b>Sohbet</b> ekranında yazdığınız her mesaj, içindeki hassas bilgiler (IP, sunucu adı, kullanıcı adı, parola, anahtar…) <b>tutarlı yer tutucularla</b> değiştirildikten sonra yapay zekâya gider; gelen yanıt otomatik olarak gerçek değerlere geri çevrilir. <b>Maskele</b> ekranında ise bir metni elle maskeleyip başka bir yapay zekâ aracına kopyalayabilirsiniz.',
    [
        'sohbet'    => 'Sohbet',
        'sohbet-ekran' => 'Sohbet ekranı',
        'gorsel'    => 'Ekran görüntüsü ekleme',
        'sohbet-gizlilik' => 'Sohbetlerin saklanması',
        'hizli'     => 'Maskele: hızlı başlangıç',
        'girdi'     => 'Metni yükleme',
        'secenek'   => 'Mod ve seçenekler',
        'neler'     => 'Neler maskelenir?',
        'cikti'     => 'Çıktı sekmeleri',
        'ai'        => 'Yapay zekâ ile çalışma',
        'uyari'     => 'Uyarılar ve istatistikler',
        'gizlilik'  => 'Gizlilik',
        'iyi'       => 'İyi uygulamalar',
        'sss'       => 'Sık sorulan sorular',
    ],
    'admin-guide.php',
    'Yönetici kılavuzu'
);
?>

<?php g_section('sohbet', 'Sohbet'); ?>
<p><b>Sohbet</b> ekranı (üst menüdeki ilk sekme), bağlı yapay zekâ modelleriyle güvenli biçimde konuşmanızı sağlar. Gerçek değerlerle yazarsınız; yapay zekâ yalnızca maskelenmiş hâlini görür.</p>
<ol>
  <li><b>Siz yazarsınız.</b> Sorunuzu yazın; log, script veya konfigürasyonu olduğu gibi yapıştırın.</li>
  <li><b>Maskink maskeler.</b> Mesaj sunucuda maskelenir; yapay zekâya yalnızca <?= g_tok('<IP_1>', '#3b82f6') ?>, <?= g_tok('<USER_1>', '#16a34a') ?> gibi tokenlar gider.</li>
  <li><b>Yapay zekâ yanıtlar.</b> Model, yanıtında ve önerdiği komutlarda aynı tokenları kullanır.</li>
  <li><b>Yanıt geri çevrilir.</b> Tokenlar tarayıcınızda gerçek değerlere dönüştürülür; komutları doğrudan kopyalayıp kullanabilirsiniz.</li>
</ol>
<?php g_example(
    "web01.acme.local (10.20.30.40) sunucusuna\nssh admin@10.20.30.40 ile bağlanamıyorum.",
    "<FQDN_1> (<IP_1>) sunucusuna\nssh <USER_1>@<IP_1> ile bağlanamıyorum."
); ?>
<p>Yanıtta model örneğin <code>ssh -vvv &lt;USER_1&gt;@&lt;IP_1&gt;</code> yazar; siz ekranda <code>ssh -vvv admin@10.20.30.40</code> olarak görürsünüz. Geri çevrilen değerler kategori rengiyle işaretlenir; üzerine geldiğinizde yapay zekânın hangi tokenı gördüğü yazar.</p>

<h3>Sohbet boyunca tutarlı tokenlar</h3>
<p>Bir değer sohbetin ilk mesajında hangi tokenı aldıysa sonraki bütün mesajlarda da aynı tokenı alır. Daha önce maskelenmiş bir değeri sonraki mesajda bağlamsız yazsanız bile (ör. önce <code>web01.acme.local</code>, sonra yalnızca <code>web01</code>) tanınır ve maskelenir. Böylece yapay zekâ "hangi sunucu, hangi kullanıcı" ilişkisini sohbet boyunca kaybetmez.</p>
<?php g_note('Maskink otomatik bir yardımcıdır. Kurallar düz cümle içinde bağlamsız geçen bazı değerleri (ör. “admin kullanıcısıyla”) yakalayamayabilir. Hassas içerik gönderirken <b>Göndermeden önce önizle</b> seçeneğini açın.', 'warn'); ?>
<?php if ($needLogin): ?>
  <?php g_note('Bu kurulumda sohbet ve maskeleme için giriş zorunlu tutulmuştur. Gönderme giriş isterse yöneticinizden erişim bilgisi alın.', 'warn'); ?>
<?php endif; ?>

<?php g_section('sohbet-ekran', 'Sohbet ekranı'); ?>
<h3>Model seçimi</h3>
<p>Üstteki <b>Model</b> listesi bağlı servislerdeki kullanılabilir modelleri gösterir (yapılandırılmış bağlantılar). Modelin yanındaki sayı bağlam uzunluğudur (ör. <code>128K</code>): ne kadar uzun sohbet ve log alabileceğini gösterir. Seçiminiz sohbet başına hatırlanır. Liste güncel değilse yanındaki <b>yenile</b> düğmesine basın.</p>
<ul>
  <li>Büyük, genel amaçlı modeller analiz ve açıklama için iyi bir varsayılandır.</li>
  <li>Kod (coder) modelleri script yazma ve kod düzeltme için daha uygundur.</li>
  <li>Küçük modeller daha hızlı yanıt verir. Listede <b>· görsel</b> yazan modeller ekran görüntüsünü de görebilir.</li>
</ul>
<p>Bazı modeller yanıt vermeden önce “düşünür”. Bu düşünme metni yanıtın üstünde katlanabilir <b>Düşünme süreci</b> bölümünde gösterilir; o da gerçek değerlere geri çevrilir.</p>

<h3>Mesaj yazma</h3>
<ul>
  <li><kbd>Enter</kbd> mesajı gönderir, <kbd>Shift</kbd> + <kbd>Enter</kbd> yeni satır açar.</li>
  <li><b>Dosya:</b> Metin dosyasının içeriğini mesaja ekler; dosyayı yazma alanına sürükleyip bırakabilirsiniz de. Resim dosyaları <a href="#gorsel">ekran görüntüsü</a> olarak eklenir. Tek mesajda en fazla <b><?= number_format($maxKb, 0, ',', '.') ?> KB</b> gönderilebilir.</li>
  <li><b>Göndermeden önce önizle:</b> İşaretliyse gönder düğmesine bastığınızda yapay zekâya gidecek maskelenmiş metin gösterilir; kontrol edip <b>Gönder</b> ya da <b>Vazgeç</b> dersiniz.</li>
  <li><b>Durdur:</b> Yanıt gelirken akışı keser; o ana kadar gelen kısım kalır.</li>
</ul>
<p>Gönderdiğiniz mesajın altında kaç değerin maskelendiği yazar (<b>4 değer maskelendi</b>). Mesajda maskelenen değerler renkli işaretlenir; üzerine geldiğinizde yapay zekâya hangi tokenla gittiği görünür. Maskelenmemiş bir satırda <code>password</code>, <code>token</code> gibi riskli bir kelime geçiyorsa <b>risk uyarısı</b> çıkar.</p>

<h3>Yanıt düğmeleri</h3>
<ul>
  <li><b>Kopyala:</b> Yanıtı gerçek değerlerle kopyalar.</li>
  <li><b>Maskeli kopyala:</b> Yanıtı tokenlı hâliyle kopyalar (ör. başkasıyla paylaşmak için).</li>
  <li><b>Yeniden oluştur:</b> Son yanıtı silip aynı soruyu yeniden sorar.</li>
  <li>Kod bloklarının sağ üstündeki <b>Kopyala</b>, komutu gerçek değerlerle kopyalar.</li>
</ul>
<p>Yanıtın altında modelin adı, süre ve kullanılan token sayısı görünür. Model eşlemede olmayan bir token uydurursa (ör. hiç geçmemiş bir <?= g_tok('<IP_9>', '#b45309') ?>) sarı kesik çizgiyle işaretlenir ve uyarı verilir; bu değer geri çevrilemez.</p>

<h3>Yapay zekânın gördüğü</h3>
<p>Sağ üstteki <b>Yapay zekânın gördüğü</b> kutusunu işaretlerseniz bütün sohbet, yapay zekâya giden ve ondan gelen hâliyle, yani tokenlarla gösterilir. Neyin gizlendiğini kontrol etmek için kullanın; kutuyu kaldırınca gerçek değerlere döner.</p>

<h3>Eşleme</h3>
<p><b>Eşleme</b> düğmesi bu sohbette hangi tokenın hangi gerçek değere karşılık geldiğini listeler; yanındaki sayı maskelenmiş farklı değer sayısıdır. <b>JSON indir</b> ile kaydedilen dosya, Maskele ekranının <b>Geri çevir</b> sekmesinde de yüklenebilir.</p>
<?php g_note('Eşleme tablosu ve JSON dosyası gerçek değerleri içerir. Ekran paylaşırken açık bırakmayın; dosyayı kimseyle ve yapay zekâyla paylaşmayın.', 'warn'); ?>

<h3>Sohbet listesi</h3>
<p>Soldaki listede eski sohbetleriniz durur. <b>Yeni sohbet</b> ile boş bir sohbet açılır; her sohbetin kendi eşleme tablosu vardır, yani tokenlar sohbetler arasında paylaşılmaz. Bir sohbetin üzerine gelip çöp kutusu simgesiyle silebilirsiniz. Dar ekranlarda liste, üst çubuktaki menü düğmesiyle açılır.</p>

<?php g_section('gorsel', 'Ekran görüntüsü ekleme'); ?>
<p>Bir hata ekranını, tabloyu ya da konsol çıktısını ekran görüntüsü olarak da sorabilirsiniz. Görsel yapay zekâya gitmeden önce <b>hassas yazıların üstü kapatılır</b>.</p>
<ol>
  <li><b>Ekleyin.</b> Ekran görüntüsünü yazma alanına <kbd>Ctrl</kbd> + <kbd>V</kbd> ile yapıştırın, sürükleyip bırakın ya da <b>Dosya</b> ile seçin. Bir mesaja en fazla 4 görsel eklenebilir.</li>
  <li><b>Görsel okunur.</b> Görseldeki yazılar <b>sizin bilgisayarınızda</b> iki kez okunur (OCR): bir kez sayfa düzeniyle, bir kez tablo hücreleri gibi dağınık yazılar için. Görsel bu iş için hiçbir yere gönderilmez. İlk kullanımda okuma dosyalarının yüklenmesi birkaç saniye sürebilir.</li>
  <li><b>Üstü kapatılır.</b> Okunan metin Maskink kurallarıyla maskelenir ve maskelenen her değerin üstüne tokenı yazılır (ör. IP adresinin yerinde <?= g_tok('<IP_1>', '#3b82f6') ?> yazar). Başlığı <i>Kullanıcı, User, Hesap, Sunucu, Host, Ad Soyad</i> gibi olan tablo sütunlarındaki hücreler, yanlarında bağlam olmasa da maskelenir.</li>
  <li><b>Kontrol edin ve düzeltin.</b> Açılan pencerede yapay zekâya gidecek görseli görürsünüz (aşağıdaki tablo).</li>
  <li><b>Mesaja ekle</b> deyin, sorunuzu yazıp gönderin. Göndermeden önce ek kartındaki <b>Düzenle</b> ile pencereyi yeniden açabilirsiniz.</li>
</ol>
<div class="mk-table"><table>
  <thead><tr><th>Ne yapmak istiyorsunuz?</th><th>Nasıl?</th></tr></thead>
  <tbody>
    <tr><td>Açık kalmış bir kullanıcı adını, sunucu adını… maskelemek</td><td>Kelimeye <b>tıklayın</b> (birden çok kelime için <b>sürükleyerek seçin</b>) ve açılan menüden kategoriyi seçin: <i>Kullanıcı adı, Sunucu adı, Kişi adı…</i> Değer görselin ve metnin <b>her yerinde</b> aynı tokenla maskelenir; mesajınızda geçiyorsa orada da.</td></tr>
    <tr><td>Yanlış kapatılmış bir yeri açmak</td><td>Kapatılmış alana <b>sağ tıklayın</b>: maskesi hemen kaldırılır, o değer her yerde açık kalır. (Sol tık aynı işlemi menüyle yapar.)</td></tr>
    <tr><td>Yazı olmayan bir yeri (resim, imza, logo) kapatmak</td><td>Boş alanda <b>sürükleyerek</b> koyu bir kutu çizin. Kutuya sağ tıklamak onu siler.</td></tr>
    <tr><td>Yaptığım değişikliği geri almak</td><td><b>Geri al</b>: maskeleme, maske kaldırma ve kutu işlemlerini sırayla geri alır.</td></tr>
    <tr><td>Kapatılmamış hâli görmek</td><td><b>Orijinali göster</b> kutusunu işaretleyin; yalnızca sizin ekranınızda gösterilir.</td></tr>
  </tbody>
</table></div>
<p><b>Turuncu kesik çerçeveli</b> kelimeler kontrol etmeniz gerekenlerdir: tam okunamamış ya da kullanıcı adı / sunucu adı gibi görünen (<code>j.doe</code>, <code>svc_backup</code>, <code>admin01</code>) ama maskelenmemiş kelimeler. Gerekiyorsa tıklayıp maskeleyin ya da <b>Turuncu işaretlileri kutuyla kapat</b> deyin.</p>
<p>Yapay zekâ hem üstü kapatılmış görseli hem de görselden okunan metnin maskelenmiş hâlini alır. Yanıtında <?= g_tok('<IP_1>', '#3b82f6') ?> derse siz gerçek IP adresini görürsünüz. Mesajınızda ve görselde geçen aynı değer aynı tokenı alır.</p>
<ul>
  <li><b>Görsel destekleyen modeller</b> (listede <b>· görsel</b> yazanlar) görseli görür; tablo düzenini, renkleri ve simgeleri de yorumlayabilir.</li>
  <li><b>Diğer modellere</b> görsel gönderilmez; yalnızca görselden okunan metin maskelenerek gider. Ek kartında bu durum yazar.</li>
  <li>Gönderdiğiniz mesajda görsel durur; üzerine tıklayınca büyür. Sağ üstteki <b>Yapay zekânın gördüğü</b> kutusu metinde olduğu gibi görselde de geçiş yapar: kapalıyken orijinal görsel, açıkken üstü kapatılmış hâli görünür. Büyütme penceresinde de <b>Orijinali göster</b> düğmesiyle geçebilirsiniz. <b>Görüntüden okunan metin</b> bölümünde okunan yazıyı görebilirsiniz.</li>
</ul>
<?php g_note('Görseldeki yazıların okunması kusursuz değildir: çok küçük, bulanık, eğik ya da resim içine gömülü yazılar okunamayabilir ve bu yüzden kapatılmayabilir. Ayrıca kurallar düz metinde olduğu gibi her değeri tanımayabilir. Göndermeden önce pencerede görseli mutlaka gözden geçirin; yüz, imza, logo gibi yazı olmayan hassas alanları da elle kapatın.', 'warn'); ?>

<?php g_section('sohbet-gizlilik', 'Sohbetlerin saklanması'); ?>
<ul>
  <li>Sohbetler ve eşleme tabloları <b>yalnızca kullandığınız bilgisayarda, o tarayıcıda</b> saklanır. Sunucuya veya veritabanına kaydedilmez.</li>
  <li>Başka bir bilgisayardan ya da başka bir tarayıcıdan girdiğinizde sohbetleriniz <b>görünmez</b>; başkaları da sizin sohbetlerinizi göremez.</li>
  <li>Gizli (InPrivate) pencerede açılan sohbetler pencere kapanınca silinir. Tarayıcının site verilerini temizlemek de sohbetleri siler.</li>
  <li>Eklediğiniz ekran görüntülerinin hem orijinali hem üstü kapatılmış hâli, gerçek değerli metinler gibi <b>yalnızca bu tarayıcıda</b> saklanır; yapay zekâya yalnızca kapatılmış hâli gider. Tarayıcının depolama alanı dolarsa önce en eski sohbetlerdeki orijinal görseller, sonra kapatılmış görseller silinir; metin ve eşleme korunur.</li>
  <li>Mesajlar sunucudan yapay zekâya geçerken yalnızca bellekte tutulur. Yapay zekâya giden metinde gerçek değerler yoktur.</li>
</ul>
<?php g_note('Sohbetler tarayıcıda <b>gerçek değerleriyle</b> durur. Bilgisayarı başkalarıyla aynı Windows kullanıcısıyla paylaşıyorsanız, işiniz bitince sol alttaki <b>Tüm sohbetleri sil</b> düğmesini kullanın.', 'warn'); ?>

<?php g_section('hizli', 'Maskele: hızlı başlangıç'); ?>
<p><b>Maskele</b> ekranı, bir metni sohbete göndermeden yalnızca maskelemek içindir: maskelenmiş çıktıyı başka bir yapay zekâ aracına veya bir kayda kopyalayabilir, gelen yanıtı yine burada geri çevirebilirsiniz.</p>
<ol>
  <li>Üst menüden <b>Maskele</b> sayfasını açın.</li>
  <li>Log veya script içeriğini soldaki <b>Girdi</b> alanına yapıştırın ya da dosyayı alana sürükleyip bırakın.</li>
  <li><b>Maskele</b> düğmesine basın (kısayol: <kbd>Ctrl</kbd> + <kbd>Enter</kbd>).</li>
  <li>Sağdaki <b>Maskelenmiş</b> sekmesini kontrol edin; gözden kaçan bir değer var mı bakın.</li>
  <li><b>Kopyala</b> ya da <b>Eşleme ▾ → Yapay zekâ açıklamasıyla kopyala</b> ile çıktıyı yapay zekâya verin.</li>
  <li>Yapay zekânın yanıtını <b>Geri çevir</b> sekmesine yapıştırarak tokenları gerçek değerlere dönüştürün.</li>
</ol>

<?php g_example(
    "Nov  2 09:14:07 web01 sshd[2211]: Accepted password for ahmet.yilmaz from 10.20.30.40 port 52144\nmysql -u app_user -p'S3cr3t!2024' -h db01.corp.local",
    "Nov  2 09:14:07 <HOST_1> sshd[2211]: Accepted password for <USER_1> from <IP_1> port 52144\nmysql -u <USER_2> -p'<PASS_1>' -h <FQDN_1>"
); ?>
<p>Aynı değer metnin her yerinde <b>aynı token</b> ile değiştirilir: <code>web01</code> yüz kez geçse de hep <?= g_tok('<HOST_1>', '#f59e0b') ?> olur. Bu sayede yapay zekâ "hangi sunucuda ne oldu" ilişkisini kurabilir.</p>
<?php if ($needLogin): ?>
  <?php g_note('Bu kurulumda maskeleme ekranı için giriş zorunlu tutulmuştur. Maskele düğmesi giriş isterse yöneticinizden erişim bilgisi alın.', 'warn'); ?>
<?php endif; ?>

<?php g_section('girdi', 'Metni yükleme'); ?>
<ul>
  <li><b>Yapıştırma:</b> İçeriği doğrudan Girdi alanına yapıştırın.</li>
  <li><b>Dosya aç:</b> <code>.log</code>, <code>.txt</code>, <code>.sh</code>, <code>.ps1</code>, <code>.py</code>, <code>.conf</code>, <code>.cfg</code>, <code>.ini</code>, <code>.json</code>, <code>.xml</code>, <code>.yml</code>, <code>.csv</code>, <code>.env</code>, <code>.sql</code> ve benzeri metin dosyalarını seçebilirsiniz.</li>
  <li><b>Sürükle-bırak:</b> Dosyayı Girdi alanının üzerine bırakın.</li>
  <li><b>Karakter kodlaması:</b> UTF-8, UTF-16 (Windows olay dışa aktarımları) ve Türkçe Windows-1254 dosyaları otomatik tanınır; Türkçe karakterler bozulmaz.</li>
  <li><b>Örnek:</b> Aracı denemek için hazır uydurma örnekler yükler (karma log, bash, PowerShell, switch/router konfigürasyonu).</li>
  <li><b>Temizle:</b> Girdiyi ve sonuçları siler.</li>
</ul>
<p>Tek seferde en fazla <b><?= number_format($maxKb, 0, ',', '.') ?> KB</b> metin maskelenebilir. Daha büyük dosyaları parçalara bölün.</p>

<?php g_section('secenek', 'Mod ve seçenekler'); ?>
<h3>Mod: Otomatik / Log / Script</h3>
<p>Bazı kurallar yalnızca belirli içerik türünde anlamlıdır. Örneğin script modunda <code>$user.Name</code> gibi kod ifadeleri kullanıcı adı sanılmaz; log modunda ise syslog başlığındaki sunucu adı yakalanır.</p>
<ul>
  <li><b>Otomatik:</b> Maskink satır yapısına bakarak içeriğin log mu script mi olduğunu tahmin eder. Çoğu durumda bunu kullanın.</li>
  <li><b>Log:</b> Sunucu, firewall, switch, NAC, uygulama logları için.</li>
  <li><b>Script:</b> bash, PowerShell, Python ve benzeri kod/konfigürasyon dosyaları için.</li>
</ul>
<p>Hangi modun kullanıldığı çıktının altındaki istatistik satırında <b>Mod:</b> olarak görünür.</p>

<h3>Bu çalıştırmada maskelenecek kategoriler</h3>
<p>Girdi alanının altındaki bu bölümü açarak bazı kategorileri o anki çalışma için kapatabilirsiniz. Örneğin bir ağ sorunu analiz ediyorsanız IP adreslerini açık bırakmak isteyebilirsiniz. Seçiminiz tarayıcınızda hatırlanır.</p>
<?php g_note('Parola ve Anahtar/Token kategorilerini kapatmayın. Bu bilgiler hiçbir analiz için gerekli değildir ve sızdığında en büyük zararı verir.', 'warn'); ?>

<h3>Canlı</h3>
<p>İşaretliyse siz yazdıkça ya da yapıştırdıkça çıktı kısa bir gecikmeyle kendiliğinden güncellenir.</p>

<h3>Yayılım</h3>
<p>Bir değer bağlamıyla bulunduğunda (ör. <code>user=ahmet</code>), aynı değer metnin başka bir yerinde bağlamsız geçse de (ör. <code>ahmet oturum açtı</code>) maskelenir. Varsayılan olarak açıktır ve açık kalması önerilir.</p>

<?php g_section('neler', 'Neler maskelenir?'); ?>
<p>Maskink aşağıdaki kategorileri tanır. Token biçimi yöneticiniz tarafından değiştirilebilir; tablo bu kurulumun güncel ayarlarını gösterir.</p>
<?php g_category_table(); ?>
<p>Tanıma örnekleri:</p>
<ul>
  <li><b>Parolalar:</b> <code>password="…"</code>, <code>pwd=…</code>, <code>-p'…'</code>, <code>ConvertTo-SecureString "…"</code>, <code>sshpass -p</code>, URL içindeki <code>kullanici:parola@</code>, Cisco <code>enable secret</code>, SNMP community, <code>/etc/shadow</code> hash'leri…</li>
  <li><b>Anahtarlar:</b> PEM/SSH özel anahtar blokları, JWT, AWS/GitHub/Slack vb. token kalıpları, <code>Authorization: Bearer …</code>, çerezler, yüksek entropili gizli değerler.</li>
  <li><b>Kullanıcılar:</b> <code>ad.soyad</code> kalıbı, <code>CORP\kullanici</code>, <code>user@host</code>, Windows olay loglarındaki hesap adları, <code>C:\Users\…</code> ve <code>/home/…</code> yolları.</li>
  <li><b>Ağ:</b> IPv4/IPv6, MAC, FQDN, URL içindeki sunucu adı, UNC yolları (<code>\\sunucu\paylasim</code>), LDAP DN'leri.</li>
  <li><b>Kişisel veriler:</b> Ad Soyad, e-posta, telefon, algoritması doğrulanmış TCKN, IBAN ve kart numarası.</li>
</ul>
<p>Kurumunuza özgü sunucu, proje veya müşteri adları kalıpla tanınamıyorsa yöneticiniz bunları <b>Özel sözlük</b>e ekleyebilir.</p>

<?php g_section('cikti', 'Çıktı sekmeleri'); ?>
<h3>Maskelenmiş</h3>
<p>Yapay zekâya verilecek metindir. Tokenlar kategori rengiyle gösterilir; bir tokena tıkladığınızda aynı token metnin her yerinde vurgulanır.</p>
<h3>İşaretli orijinal</h3>
<p>Orijinal metni, maskelenen kısımları renkli işaretleyerek gösterir. Neyin maskelendiğini, neyin gözden kaçtığını kontrol etmek içindir.</p>
<?php g_note('Bu sekme gerçek verileri içerir. Yapay zekâya bu sekmeyi değil, <b>Maskelenmiş</b> sekmesini kopyalayın.', 'warn'); ?>
<h3>Eşleme tablosu</h3>
<p>Her tokenın hangi gerçek değere karşılık geldiğini, kaç kez geçtiğini ve hangi kuralın bulduğunu listeler. Orijinal değerler varsayılan olarak bulanıktır; görmek için <b>Orijinal değerleri göster</b>i işaretleyin. Token, kategori veya kural adına göre arama yapabilirsiniz.</p>
<h3>Geri çevir</h3>
<p>Yapay zekânın tokenlı yanıtını buraya yapıştırıp <b>Geri çevir</b>e basın; tokenlar mevcut eşleme tablosuyla gerçek değerlere dönüştürülür. Sayfayı kapattıysanız, daha önce indirdiğiniz eşleme JSON dosyasını <b>Eşleme JSON yükle</b> ile yükleyerek de geri çevirebilirsiniz. İşlem tamamen tarayıcınızda yapılır.</p>

<h3>Üst düğmeler</h3>
<ul>
  <li><b>Kopyala:</b> Maskelenmiş metni panoya kopyalar.</li>
  <li><b>İndir:</b> Maskelenmiş metni dosya olarak indirir.</li>
  <li><b>Eşleme ▾ → JSON / CSV indir:</b> Eşleme tablosunu kaydeder. Bu dosya <b>gerçek değerleri içerir</b>; güvenli bir yerde saklayın, kimseyle ve yapay zekâyla paylaşmayın.</li>
  <li><b>Eşleme ▾ → Yapay zekâ açıklamasıyla kopyala:</b> Maskelenmiş metnin başına, yapay zekâya tokenların ne anlama geldiğini ve onları aynen koruması gerektiğini anlatan kısa bir açıklama ekleyerek kopyalar. Gerçek değerler eklenmez.</li>
</ul>

<?php g_section('ai', 'Yapay zekâ ile çalışma'); ?>
<?php g_note('Maskink\'e bağlı yapay zekâ modellerini kullanacaksanız aşağıdaki adımlara gerek yok: <a class="font-semibold underline" href="chat.php">Sohbet</a> ekranı maskeleme ve geri çevirmeyi her mesajda kendiliğinden yapar. Bu adımlar başka bir yapay zekâ aracına elle kopyalama içindir.', 'info'); ?>
<ol>
  <li>Metni maskeleyin ve <b>Maskelenmiş</b> sekmesini gözden geçirin.</li>
  <li><b>Eşleme ▾ → Yapay zekâ açıklamasıyla kopyala</b>yı seçin ve sorunuzla birlikte yapay zekâya yapıştırın.</li>
  <li>Yapay zekâ yanıtında <?= g_tok('<HOST_1>', '#f59e0b') ?>, <?= g_tok('<USER_2>', '#16a34a') ?> gibi tokenları kullanacaktır.</li>
  <li>Yanıtı (ör. önerilen komutları) <b>Geri çevir</b> sekmesine yapıştırın; gerçek değerlerle uygulanabilir hâle gelir.</li>
</ol>
<?php g_note('Uzun bir çalışmada eşleme tablosunu kaybetmemek için <b>Eşleme ▾ → JSON indir</b> ile yedek alın. Aynı konuşmaya yeni bir log eklemeniz gerekirse, yeni çalıştırmada numaraların değişebileceğini unutmayın; yeni çıktıyı ayrı bir parça olarak belirtin.', 'tip'); ?>

<?php g_section('uyari', 'Uyarılar ve istatistikler'); ?>
<p>Bir satırda <code>password</code>, <code>secret</code>, <code>token</code> gibi hassas bir anahtar kelime geçtiği hâlde o satırda hiçbir şey maskelenmediyse, çıktının üstünde sarı bir uyarı görünür. Satır numarasına tıklayarak ilgili satıra gidebilirsiniz. Bu durum bir değerin gözden kaçmış olabileceğini gösterir; satırı mutlaka kontrol edin ve gerekiyorsa yöneticinize bildirin.</p>
<p>Çıktının altındaki istatistik satırı; algılanan modu, kategori başına benzersiz değer ve geçiş sayısını, toplam eşleşmeyi ve işlem süresini gösterir. Bir kategoriye tıklayarak eşleme tablosunu o kategoriye göre süzebilirsiniz.</p>

<?php g_section('gizlilik', 'Gizlilik'); ?>
<ul>
  <li>Maskelediğiniz metin sunucuda <b>saklanmaz</b>; yalnızca işlem süresince bellekte tutulur.</li>
  <li>Maskele ekranında eşleme tablosu (token → gerçek değer) yalnızca açık olan tarayıcı sayfasında durur; sayfayı kapattığınızda silinir. Sohbet ekranının saklama kuralları için <a href="#sohbet-gizlilik" class="font-medium text-violet-700 underline dark:text-violet-300">Sohbetlerin saklanması</a> bölümüne bakın.</li>
  <li>Yönetici istatistik tutmayı açtıysa yalnızca sayısal bilgiler (karakter sayısı, eşleşme sayısı, süre) kaydedilir; içerik asla kaydedilmez.</li>
  <li>Tarayıcınızda yalnızca tercihleriniz (mod, Canlı, Yayılım, kapattığınız kategoriler) hatırlanır.</li>
</ul>

<?php g_section('iyi', 'İyi uygulamalar'); ?>
<ul>
  <li><b>Her zaman gözden geçirin.</b> Maskink otomatik bir yardımcıdır, yüzde yüz garanti vermez. Göndermeden önce Maskelenmiş çıktıya hızlıca göz atın.</li>
  <li><b>Gereğinden fazlasını göndermeyin.</b> Sorunla ilgili satırları seçin; tüm log dosyasını göndermek yerine ilgili zaman aralığını alın.</li>
  <li><b>Eşleme dosyalarını koruyun.</b> JSON/CSV eşleme dosyaları gerçek verileri içerir; işiniz bitince silin.</li>
  <li><b>Gözden kaçanları bildirin.</b> Maskelenmemiş bir parola, sunucu adı veya kurum terimi görürseniz yöneticinize örnek bir satırla (gerçek değeri değiştirerek) bildirin; kural eklenebilir.</li>
  <li><b>Ekran görüntülerine dikkat.</b> İşaretli orijinal veya eşleme tablosu açıkken ekran paylaşmayın.</li>
</ul>

<?php g_section('sss', 'Sık sorulan sorular'); ?>
<h3>Sohbetimi başka bir bilgisayardan görebilir miyim?</h3>
<p>Hayır. Sohbetler yalnızca yazdığınız bilgisayarın tarayıcısında saklanır. Bir yanıtı saklamak istiyorsanız <b>Kopyala</b> ile alıp kendi notlarınıza ekleyin.</p>
<h3>Sohbette yapay zekâ cevabında hâlâ &lt;IP_1&gt; gibi tokenlar görüyorum.</h3>
<p>Model tokenı bozarak yazdıysa (ör. boşluk ekleyerek) ya da eşlemede olmayan bir numara uydurduysa geri çevrilemez; bu tokenlar sarı işaretlenir. <b>Yeniden oluştur</b>u deneyin veya modele “yer tutucuları aynen kullan” diye hatırlatın. <b>Yapay zekânın gördüğü</b> kutusunun işaretli olmadığından da emin olun.</p>
<h3>Model listesi boş ya da “Model listesi alınamadı” diyor.</h3>
<p>Yapay zekâ servisine şu anda ulaşılamıyor olabilir. Birkaç dakika sonra model listesinin yanındaki yenile düğmesine basın; sorun sürerse yöneticinize bildirin.</p>
<h3>Yanıt yarıda kesildi.</h3>
<p>Uzun sohbetlerde modelin bağlam sınırı dolabilir. <b>Yeni sohbet</b> açıp yalnızca gerekli bilgiyle yeniden sorun ya da bağlamı daha uzun bir model seçin.</p>
<h3>Bir değer maskelenmedi, ne yapmalıyım?</h3>
<p>Önce o kategorinin "Bu çalıştırmada maskelenecek kategoriler" bölümünde açık olduğundan ve doğru modu seçtiğinizden emin olun. Değer hâlâ maskelenmiyorsa göndermeden önce elle değiştirin ve yöneticinize bildirin.</p>
<h3>Maskelenmemesi gereken bir şey maskelendi.</h3>
<p>Analiz için gerekliyse ilgili kategoriyi o çalıştırma için kapatabilirsiniz. Sık karşılaşılan bir durumsa (ör. iç ağ IP aralıkları, <code>localhost</code>) yöneticiniz bunu kalıcı istisna listesine ekleyebilir.</p>
<h3>Tokenlardaki numaralar her seferinde aynı mı?</h3>
<p>Numaralar aynı çalıştırma içinde tutarlıdır, ancak farklı bir metinle yeniden maskelediğinizde değişebilir. Yöneticiniz token biçimini <code>{hash}</code> kullanacak şekilde ayarladıysa aynı değer her zaman aynı tokenı alır.</p>
<h3>Yapay zekâ tokenları değiştirdi, geri çevirme çalışmıyor.</h3>
<p>Geri çevirme yalnızca tokenlar aynen korunduğunda çalışır. Yapay zekâya tokenları değiştirmemesini söyleyin; <b>Yapay zekâ açıklamasıyla kopyala</b> bunu otomatik olarak belirtir.</p>
<h3>Sayfayı yeniledim, eşleme kayboldu.</h3>
<p>Eşleme güvenlik nedeniyle yalnızca sayfada tutulur. Önceden indirdiğiniz eşleme JSON dosyası varsa Geri çevir sekmesinden yükleyebilirsiniz; yoksa aynı girdiyi yeniden maskeleyin.</p>

<?php g_close(); ?>
