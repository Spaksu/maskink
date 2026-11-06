<?php // Admin guide – Turkish content (included by admin-guide.php)
g_open(
    'Yönetici Kılavuzu',
    'Bu kılavuz Maskink\'in maskeleme motorunu, kuralları, listeleri, yapay zekâ sohbetinin yapılandırmasını ve yönetim ayarlarını anlatır. Ayarlar ekranındaki her bölümün ne işe yaradığını, bir değerin neden maskelendiğini ya da maskelenmediğini nasıl bulacağınızı ve kurumunuza özgü kuralları nasıl ekleyeceğinizi burada bulabilirsiniz.',
    [
        'giris'      => 'Giriş ve erişim',
        'motor'      => 'Motor nasıl çalışır?',
        'genel'      => 'Genel bakış',
        'kurallar'   => 'Kurallar',
        'duzenleyici'=> 'Kural düzenleyici',
        'ornek'      => 'Örnek: yeni kural',
        'sozluk'     => 'Özel sözlük',
        'kategori'   => 'Kategoriler ve tokenlar',
        'listeler'   => 'Listeler ve istisnalar',
        'ayarlar'    => 'Genel ayarlar',
        'sohbet'     => 'Sohbet (yapay zekâ)',
        'baglanti'   => 'Yapay zekâ bağlantıları',
        'yedek'      => 'Yedekle / geri yükle',
        'guvenlik'   => 'Güvenlik ve günlük',
        'sorun'      => 'Sorun giderme',
        'teknik'     => 'Teknik notlar',
    ],
    'guide.php',
    'Kullanım kılavuzu',
    'Yönetici'
);
?>

<?php g_section('giris', 'Giriş ve erişim'); ?>
<ul>
  <li>Ayarlara üst sağdaki <b>dişli</b> simgesinden ulaşılır.</li>
  <li><b>İlk kullanım:</b> Henüz yönetici parolası yoksa Ayarlar ekranı parola belirlemenizi ister (en az 8 karakter). Ayrı bir kurulum adımı yoktur; veritabanı ve varsayılan kurallar ilk istekte otomatik oluşturulur.</li>
  <li><b>Kilitlenme:</b> Arka arkaya 5 hatalı girişte o IP adresi 5 dakika kilitlenir.</li>
  <li><b>Oturum:</b> Belirli bir süre işlem yapılmazsa oturum kapanır (süre Güvenlik bölümünden ayarlanır). Sağ üstteki <b>Çıkış</b> ile oturumu elle kapatabilirsiniz.</li>
  <li><b>Maskeleme ekranı:</b> Varsayılan olarak ağdaki herkes maskeleme yapabilir; ayarlar her zaman parola korumalıdır. İsterseniz maskeleme için de giriş zorunlu kılabilirsiniz.</li>
</ul>
<?php g_note('Parolayı unutursanız veritabanındaki <code>mk_settings</code> tablosundan <code>admin_hash</code> satırını silin; bir sonraki Ayarlar girişinde yeni parola belirleme ekranı açılır.', 'info'); ?>

<?php g_section('motor', 'Motor nasıl çalışır?'); ?>
<p>Her maskeleme isteğinde motor şu adımları sırayla uygular:</p>
<ol>
  <li><b>Mod belirleme.</b> Kullanıcı Otomatik seçtiyse satır yapısına bakılarak içerik <i>log</i> ya da <i>script</i> olarak sınıflandırılır. Kuralların bir kısmı yalnızca bir modda çalışır.</li>
  <li><b>Aday toplama.</b> Etkin ve moda uyan tüm kurallar metin üzerinde çalıştırılır; her eşleşme (ya da kuralın yakalama grubu) bir <i>aday</i> olur.</li>
  <li><b>Süzme ve doğrulama.</b> Her aday; tutma listeleri, değişken referansı kontrolü, uzunluk sınırları, hariç tutma regex'i ve doğrulayıcılardan (Luhn, TCKN, IBAN, entropi…) geçirilir. Elenen adaylar <i>red nedeni</i> ile kaydedilir.</li>
  <li><b>Yeniden sınıflandırma.</b> İzin verilen kurallarda değerin kendisine bakılarak kategori düzeltilir: IP gibi görünen değer IP olur, <code>CORP\ahmet</code> DOMAIN + USER olarak bölünür, <code>PC01$</code> HOST, noktalı ad FQDN olur.</li>
  <li><b>Çakışma çözümü.</b> Üst üste binen adaylardan önce <b>önceliği yüksek</b> olan, eşitse <b>uzun</b> olan, o da eşitse <b>önce başlayan</b> kazanır.</li>
  <li><b>Yayılım.</b> Bulunan değerler (kategorisinde yayılım açıksa ve en az uzunluğu sağlıyorsa) metnin başka yerlerinde bağlamsız geçtiklerinde de maskelenir. FQDN'in ilk etiketi (<code>db01.corp.local</code> → <code>db01</code>) HOST olarak yayılır. Bir yol içinde (<code>/</code> veya <code>\</code> sonrası) geçen kısımlar yayılımla maskelenmez.</li>
  <li><b>Token üretimi.</b> Her benzersiz değer kategorisinin yer tutucu şablonuyla tokena çevrilir. Metinde zaten bulunan bir tokenla çakışma olmaması için numaralar atlanır.</li>
  <li><b>Sohbet eşlemesi (yalnızca Sohbet ekranında).</b> Tarayıcı, sohbetin o ana kadarki eşleme tablosunu istekle birlikte gönderir. Önceki mesajlarda maskelenmiş değerler bu mesajda bağlamsız geçse de bulunur ve <b>aynı tokenı</b> alır; yeni değerler kullanılmamış bir sonraki numarayı alır. Önceki FQDN'lerin ilk etiketi de HOST olarak aranır. Bu eşleşmeler eşleme tablosunda <i>Sohbet eşlemesi (önceki mesaj)</i> kuralıyla görünür.</li>
  <li><b>Uyarılar.</b> Hiçbir şey maskelenmeyen satırlarda risk anahtar kelimesi geçiyorsa kullanıcıya uyarı gösterilir.</li>
</ol>
<?php g_note('Bir değerin neden maskelenmediğini görmenin en hızlı yolu, ilgili kuralı açıp satırı <b>Canlı test</b> alanına yapıştırmaktır. "Reddedilen adaylar" tablosu her adayın hangi adımda neden elendiğini gösterir.', 'tip'); ?>

<?php g_section('genel', 'Genel bakış'); ?>
<p>Ayarların açılış ekranıdır:</p>
<ul>
  <li><b>Kullanım kartları:</b> Toplam çalıştırma, işlenen karakter, maskelenen değer ve ortalama süre (istatistik tutma açıksa).</li>
  <li><b>Son 14 gün:</b> Günlük çalıştırma grafiği.</li>
  <li><b>Kurallar:</b> Toplam, etkin, özel ve derlenemeyen kural sayısı; Maskink, PHP, PCRE sürümü ve JIT durumu.</li>
  <li><b>Kategori başına kural:</b> Her kategoride kaç kuralın etkin olduğu. Bir kategoriye tıklamak Kurallar ekranını o kategoriye süzer.</li>
</ul>
<p>Derlenemeyen bir regex varsa burada kırmızı bir kutuda listelenir ve kenar menüsünde <b>Kurallar</b>ın yanında sayaç belirir.</p>

<?php g_section('kurallar', 'Kurallar'); ?>
<p>Her kural bir veri türünün nasıl tanınacağını tanımlar. Liste ekranında:</p>
<ul>
  <li><b>Arama ve filtreler:</b> Ad, açıklama, desen veya anahtar içinde arama; kategori, tür ve durum (Etkin, Kapalı, Özel, Yerleşik, Hatalı) filtresi.</li>
  <li><b>Etkin anahtarı:</b> Kuralı anında açar/kapatır.</li>
  <li><b>Toplu işlem:</b> Kutucuklarla seçip <b>Seçilenleri aç / kapat</b>.</li>
  <li><b>Düzenle:</b> Kural düzenleyiciyi açar.</li>
  <li><b>Kopyala:</b> Kuralın kapalı bir kopyasını oluşturur. Yerleşik bir kuralı değiştirmeden denemek için idealdir.</li>
  <li><b>Sil:</b> Yalnızca özel kurallar silinebilir. Yerleşik kurallar silinemez; kapatılabilir ya da varsayılana döndürülebilir.</li>
</ul>
<h3>Kural türleri</h3>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400 dark:bg-slate-900"><tr><th class="px-3 py-2 font-medium">Tür</th><th class="px-3 py-2 font-medium">Ne zaman kullanılır?</th><th class="px-3 py-2 font-medium">Örnek</th></tr></thead>
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="px-3 py-2 font-medium">Regex</td><td class="px-3 py-2">Biçimi belli değerler: IP, token kalıpları, cihaz konfigürasyon satırları, kurum host adlandırma şablonu.</td><td class="px-3 py-2"><code>\bACME-[A-Z0-9]{3,}\b</code></td></tr>
    <tr><td class="px-3 py-2 font-medium">Anahtar = değer</td><td class="px-3 py-2">Değeri bir anahtar adından sonra gelen alanlar: parola, kullanıcı, sunucu alanları. Yalnızca anahtar adlarını yazarsınız; tüm yazım biçimleri otomatik eşlenir.</td><td class="px-3 py-2"><code>db password</code></td></tr>
    <tr><td class="px-3 py-2 font-medium">Sözlük</td><td class="px-3 py-2">Kalıpla tanınamayan sabit terimler: kurum sunucu adları, proje kod adları, müşteri adları.</td><td class="px-3 py-2"><code>Proje Kartal | CUSTOM</code></td></tr>
  </tbody>
</table></div>

<?php g_section('duzenleyici', 'Kural düzenleyici'); ?>
<p>Düzenleyici iki bölümden oluşur: solda kural alanları, sağda <b>Canlı test</b>. Yaptığınız her değişiklik kaydetmeden önce sağdaki örnek metinde anında denenir.</p>

<h3>Ortak alanlar</h3>
<ul>
  <li><b>Ad / Açıklama:</b> Listede ve eşleme tablosunun "Tespit eden kural" sütununda görünür.</li>
  <li><b>Ana kategori:</b> Eşleşmenin hangi tokenla maskeleneceği.</li>
  <li><b>Öncelik (0-1000):</b> Çakışmada yüksek olan kazanır. Rehber değerler: parola/anahtar ~90, bağlamsal kurallar ~70-80, genel kalıplar (IP, FQDN, ad.soyad) ~40-60.</li>
  <li><b>Çalışacağı mod:</b> Tümü, yalnızca Log ya da yalnızca Script.</li>
  <li><b>Etkin:</b> Kuralın çalışıp çalışmayacağı.</li>
</ul>

<h3>Regex kuralları</h3>
<ul>
  <li><b>Desen:</b> PCRE sözdizimi, sınırlayıcı (<code>/…/</code>) olmadan yazılır. Unicode (<code>u</code>) her zaman açıktır.</li>
  <li><b>Şablonlar:</b> <code>{{TLD}}</code> TLD listesine, <code>{{FIRSTNAME}}</code> isim sözlüğüne (küçük harf), <code>{{FIRSTNAME_CAP}}</code> büyük harfle başlayan isimlere genişler. Listeyi güncellediğinizde bu şablonu kullanan tüm kurallar da güncellenir.</li>
  <li><b>Bayraklar:</b> <code>i</code> büyük/küçük duyarsız, <code>m</code> çok satır, <code>s</code> nokta satır sonunu da eşler, <code>x</code> genişletilmiş (boşluk ve yorum serbest), <code>U</code> tembel eşleme.</li>
  <li><b>Yakalama grubu → kategori:</b> Boşsa eşleşmenin tamamı ana kategoriyle maskelenir. Grup tanımlarsanız yalnızca o gruplar maskelenir ve her gruba ayrı kategori verebilirsiniz. Örneğin <code>https://kullanici:parola@sunucu</code> için grup 1 → USER, grup 2 → PASSWORD.</li>
</ul>
<?php g_note('Bağlamı eşleştirip yalnızca değeri maskelemek için bağlamı grup dışında bırakın: <code>enable secret \d (\S+)</code> deseninde grup 1 → PASSWORD tanımlarsanız <code>enable secret 5</code> açık kalır, yalnızca hash maskelenir.', 'tip'); ?>

<h3>Anahtar = değer kuralları</h3>
<ul>
  <li><b>Anahtarlar:</b> Her satıra bir anahtar. Boşluk, <code>_</code>, <code>-</code> ve <code>.</code> esnek eşlenir; <code>user name</code> yazmanız <code>username</code>, <code>user_name</code>, <code>User-Name</code>, <code>UserName</code> ve <code>"user name"</code> yazımlarının hepsini yakalar. Büyük/küçük harf duyarsızdır.</li>
  <li><b>Desteklenen biçimler:</b> <code>key=değer</code>, <code>key: değer</code>, <code>"key": "değer"</code> (JSON), <code>$Key = 'değer'</code> (PowerShell), <code>key =&gt; değer</code> (PHP), <code>['key'] = değer</code>.</li>
  <li><b>Ön / son ek:</b> <i>Yok</i> yalnızca tam anahtarı; <i>Ön ek</i> <code>db_user</code>, <code>smtp.user</code> gibi önekli anahtarları; <i>Ön + son ek</i> <code>db_password_enc</code> gibi her iki yanı ekli anahtarları da yakalar.</li>
  <li><b>Ayraç:</b> Standart (<code>= : =&gt; := ==</code>), yalnızca <code>=</code>, ya da standart + boşluk (<code>password S3cret</code> gibi CLI biçimleri için).</li>
  <li><b>Değer regex'i:</b> Boşsa tırnaklı değer ya da bir sonraki ayraca kadar olan tırnaksız değer alınır. Belirli bir biçimi zorlamak için doldurun.</li>
  <li><b>XML etiketlerini de tara:</b> <code>&lt;password&gt;…&lt;/password&gt;</code> biçimini de yakalar.</li>
</ul>

<h3>Sözlük kuralları</h3>
<p>Her satıra bir terim yazılır. <code>terim | KATEGORİ</code> biçimiyle terime özel kategori verilebilir; <code>#</code> ile başlayan satırlar yorumdur. Terimler tam kelime olarak aranır; <b>Büyük/küçük harf duyarlı</b> seçeneği kapalıysa yazım farkı gözetilmez.</p>

<h3>Süzme ve doğrulama</h3>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="w-56 px-3 py-2 font-medium">En az / en fazla uzunluk</td><td class="px-3 py-2">Bu aralığın dışındaki değerler elenir.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Doğrulayıcı</td><td class="px-3 py-2"><b>Luhn</b> (kart), <b>TCKN algoritması</b>, <b>IBAN mod-97</b>, <b>Telefon hane sayısı</b>, <b>Shannon entropisi</b> (rastgele görünen gizli değerler), <b>ad.soyad</b> (dosya uzantısı ve kod ifadelerini eler), <b>Ad Soyad</b> (yaygın kelimeleri eler).</td></tr>
    <tr><td class="px-3 py-2 font-medium">Min. entropi</td><td class="px-3 py-2">Yalnızca entropi doğrulayıcısında kullanılır. Yükseltmek yanlış pozitifleri azaltır, gerçek anahtarları kaçırma riskini artırır. Varsayılan 4.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Hariç tutma regex'i</td><td class="px-3 py-2">Değere uygulanır; eşleşenler maskelenmez. Örn. yol değerlerini atlamak için <code>^/</code>.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Değişken referanslarını atla</td><td class="px-3 py-2"><code>$var</code>, <code>${VAR}</code>, <code>%VAR%</code>, <code>{{x}}</code>, <code>&lt;placeholder&gt;</code>, <code>****</code> gibi gerçek olmayan değerleri atlar. Scriptlerde açık kalması önerilir.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Yalnızca sayıları atla</td><td class="px-3 py-2"><code>uid=1000</code> gibi sayısal değerleri maskelemez.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Değere göre yeniden sınıflandır</td><td class="px-3 py-2">Kategoriyi değerin biçimine göre düzeltir (IP, FQDN, HOST, SID).</td></tr>
    <tr><td class="px-3 py-2 font-medium">DOMAIN\kullanıcı değerini böl</td><td class="px-3 py-2"><code>CORP\ahmet</code> → <?= g_tok('<DOMAIN_1>', '#65a30d') ?>\<?= g_tok('<USER_1>', '#16a34a') ?></td></tr>
    <tr><td class="px-3 py-2 font-medium">Yayılıma katıl</td><td class="px-3 py-2">Kapatılırsa bu kuralın bulduğu değer yalnızca bulunduğu yerde maskelenir.</td></tr>
  </tbody>
</table></div>

<h3>Canlı test</h3>
<p>Sağdaki alana örnek metin yazın ya da yapıştırın; mod seçicisiyle log/script davranışını deneyin. Test sonuçları:</p>
<ul>
  <li><b>İşaretli örnek:</b> Bulunan değerler kategori renginde; üzerine gelince kural ve kategori görünür.</li>
  <li><b>Maskelenmiş:</b> Tüm etkin kurallar ve düzenlediğiniz kuralın kaydedilmemiş hâliyle elde edilen sonuç.</li>
  <li><b>Reddedilen adaylar:</b> Elenen her değer ve nedeni. Sık görülen nedenler: <i>Tutma listesinde</i>, <i>Değişken / yer tutucu referansı</i>, <i>Yalnızca sayı</i>, <i>Kısa sayısal değer</i>, <i>Kod referansı (nesne.özellik)</i>, <i>Script değişkeni</i>, <i>Alt ağ maskesi / wildcard</i>, <i>Luhn / TCKN / IBAN doğrulaması başarısız</i>, <i>Alan adı gibi görünüyor</i>.</li>
  <li><b>Derlenmiş regex:</b> Anahtar=değer ve sözlük kurallarının ürettiği gerçek desen. Hata ayıklarken faydalıdır.</li>
</ul>
<p>Test metni tarayıcınızda kural bazında hatırlanır; sunucuya kaydedilmez. Hatalı bir regex kaydedilmeye çalışılırsa düzenleyici hatayı gösterir ve kaydetmez. Yerleşik kurallarda <b>Varsayılana döndür</b> yaptığınız değişiklikleri geri alır.</p>

<?php g_section('ornek', 'Örnek: kurum sunucu adları için kural'); ?>
<p>Kurumunuzda sunucular <code>ACME-WEB01</code>, <code>ACME-DB02</code> gibi adlandırılıyorsa:</p>
<ol>
  <li><b>Kurallar → + Yeni kural</b>.</li>
  <li>Ad: <code>Kurum sunucu adları</code>, Ana kategori: <b>Hostname (HOST)</b>, Tür: <b>Regex</b>, Öncelik: <code>75</code>, Mod: <b>Tümü</b>.</li>
  <li>Desen: <code>\bACME-[A-Z]{2,5}\d{2,3}\b</code>, bayrak: <code>i</code>.</li>
  <li>Canlı test alanına <code>ACME-WEB01 yeniden başlatıldı; acme-web01 erişilebilir</code> yazın ve iki yazımın da aynı <?= g_tok('<HOST_1>', '#f59e0b') ?> tokenını aldığını doğrulayın.</li>
  <li><b>Kaydet</b>.</li>
</ol>
<?php g_note('Kalıbı olmayan birkaç sabit ad için regex yazmak yerine <b>Özel sözlük</b>ü kullanın; daha hızlı ve hatasızdır.', 'tip'); ?>

<?php g_section('sozluk', 'Özel sözlük'); ?>
<p>Kurumunuza özgü, kalıpla tanınamayan terimler içindir: sunucu adları, proje kod adları, müşteri ve iş ortağı adları, iç sistem adları. Satır biçimi:</p>
<pre class="mk-pre mk-code my-3 rounded-lg border border-slate-200 bg-white p-3 text-[13px] dark:border-slate-800 dark:bg-slate-900"># Sunucular
ACME-DC01 | HOST
GIZLI-SRV | HOST
# Projeler ve müşteriler
Proje Kartal
AcmeBank | CUSTOM
ozel.kisi | USER</pre>
<p>Kategori yazılmazsa kuralın ana kategorisi (Özel terim) kullanılır. <b>Gelişmiş düzenleyici</b> sözlük kuralını tam düzenleyicide açar; böylece canlı test ve büyük/küçük harf ayarına ulaşabilirsiniz. Ayrı konular için birden fazla sözlük kuralı oluşturabilirsiniz (Kurallar → Yeni kural → Tür: Sözlük).</p>

<?php g_section('kategori', 'Kategoriler ve tokenlar'); ?>
<p>Her kategori için şu alanlar ayarlanır:</p>
<ul>
  <li><b>Etiket:</b> Arayüzde görünen ad.</li>
  <li><b>Token öneki:</b> Şablondaki <code>{CAT}</code> yerine geçer (ör. PASSWORD için <code>PASS</code>).</li>
  <li><b>Renk:</b> Çıktıdaki vurgu rengi.</li>
  <li><b>Yer tutucu şablonu:</b> Boşsa genel şablon kullanılır.</li>
  <li><b>Yayılım:</b> Bu kategorideki değerler metnin her yerinde aranır mı?</li>
  <li><b>Harf duyarlı:</b> Açıksa <code>Ahmet</code> ve <code>ahmet</code> ayrı token alır. Parola ve anahtar gibi büyük/küçük harfin anlam taşıdığı kategorilerde açıktır.</li>
  <li><b>Etkin / Sıra:</b> Kapalı kategoriler hiç maskelenmez ve kullanıcı ekranında görünmez; sıra, çiplerin ve listelerin düzenini belirler.</li>
</ul>
<h3>Şablon değişkenleri</h3>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="w-28 px-3 py-2"><code>{CAT}</code></td><td class="px-3 py-2">Token öneki (<code>HOST</code>)</td></tr>
    <tr><td class="px-3 py-2"><code>{cat}</code></td><td class="px-3 py-2">Küçük harfli önek (<code>host</code>)</td></tr>
    <tr><td class="px-3 py-2"><code>{n}</code></td><td class="px-3 py-2">Çalıştırma içindeki sıra numarası (1, 2, 3…)</td></tr>
    <tr><td class="px-3 py-2"><code>{hash}</code></td><td class="px-3 py-2">Değerden üretilen kararlı 8 haneli özet. Aynı değer her çalıştırmada aynı tokenı alır; özet kuruluma özgü gizli anahtarla üretildiği için tersine çevrilemez.</td></tr>
    <tr><td class="px-3 py-2"><code>{len}</code></td><td class="px-3 py-2">Orijinal değerin uzunluğu</td></tr>
    <tr><td class="px-3 py-2"><code>{stars}</code></td><td class="px-3 py-2">Değer uzunluğunda yıldız</td></tr>
  </tbody>
</table></div>
<?php g_note('Şablonda <code>{n}</code> veya <code>{hash}</code> yoksa farklı değerler aynı tokenı alır; bu durumda eşleme tablosu ve <b>Geri çevir</b> çalışmaz. <code>[MASKED_{CAT}]</code> gibi şablonları yalnızca geri çevirme gerekmeyen durumlarda kullanın.', 'warn'); ?>

<?php g_section('listeler', 'Listeler ve istisnalar'); ?>
<p>Motorun tanıma ve hariç tutma davranışını belirler. Değişiklikler kaydedildiği anda tüm kurallara uygulanır. Her listenin yanındaki <b>varsayılana dön</b> o listeyi ilk hâline getirir. Öğeler satır ya da virgülle ayrılır; <code>#</code> ile başlayan satırlar yorumdur.</p>
<div class="my-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-left text-sm">
  <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400 dark:bg-slate-900"><tr><th class="px-3 py-2 font-medium">Liste</th><th class="px-3 py-2 font-medium">Etkisi</th></tr></thead>
  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    <tr><td class="w-64 px-3 py-2 font-medium">Asla maskelenmeyecek değerler</td><td class="px-3 py-2">Tüm kategorilerde geçerli; <code>root</code>, <code>localhost</code>, <code>n/a</code>, <code>true</code> gibi değerler açık kalır.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Maskelenmeyecek IPv4 aralıkları</td><td class="px-3 py-2">Tek IP ya da CIDR. Örn. <code>127.0.0.0/8</code>. İç ağı analiz için açık bırakmak istiyorsanız <code>10.0.0.0/8</code> ekleyin; ancak bunun kurum ağ yapısını ifşa edeceğini unutmayın.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Maskelenmeyecek alan adları</td><td class="px-3 py-2">Bu alan adları ve tüm alt alan adları (FQDN ve e-posta alan adı) açık kalır: <code>microsoft.com</code>, <code>gmail.com</code>…</td></tr>
    <tr><td class="px-3 py-2 font-medium">TLD listesi</td><td class="px-3 py-2">FQDN kuralı yalnızca bu uzantılarla biten adları alan adı sayar. <b>İç alan adınızın uzantısını (corp, local, lan, intra…) mutlaka ekleyin.</b> Dosya uzantılarını (py, sh, json) eklemeyin.</td></tr>
    <tr><td class="px-3 py-2 font-medium">İsim sözlüğü</td><td class="px-3 py-2"><code>ahmet.yilmaz</code> ve <code>Ahmet Yılmaz</code> kalıpları için ilk ad listesi. Türkçe karakterli adların ASCII karşılıkları (şükrü → sukru) otomatik üretilir.</td></tr>
    <tr><td class="px-3 py-2 font-medium">ad.soyad hariç parçalar</td><td class="px-3 py-2"><code>sshd.service</code>, <code>app.log</code>, <code>config.json</code> gibi ifadelerin kullanıcı sanılmasını engeller.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Kod belirteçleri</td><td class="px-3 py-2"><code>os.path</code>, <code>self.name</code>, <code>logger.info</code> gibi kod ifadelerinin ad.soyad ya da FQDN sanılmasını engeller.</td></tr>
    <tr><td class="px-3 py-2 font-medium">FQDN ilk etiket istisnaları</td><td class="px-3 py-2">Bu etiketle başlayan adlar (<code>System.Net</code>, <code>ASP.NET</code>) FQDN sayılmaz.</td></tr>
    <tr><td class="px-3 py-2 font-medium">Ad Soyad stop-kelimeleri</td><td class="px-3 py-2">"Can Not", "Deniz Error" gibi isimle başlayan ama kişi olmayan ifadeleri eler.</td></tr>
  </tbody>
</table></div>

<?php g_section('ayarlar', 'Genel ayarlar'); ?>
<ul>
  <li><b>Genel yer tutucu şablonu:</b> Tüm kategoriler için varsayılan token biçimi (varsayılan <code>&lt;{CAT}_{n}&gt;</code>). Hazır seçeneklere tıklayarak önizleyebilirsiniz.</li>
  <li><b>Yayılım:</b> Genel açma/kapama. Kategori bazındaki ayar ayrıca uygulanır.</li>
  <li><b>Yayılım için en az uzunluk:</b> <code>it</code>, <code>db</code> gibi kısa değerlerin metnin her yerinde maskelenmesini önler.</li>
  <li><b>Varsayılan mod:</b> Kullanıcı ekranı ilk açıldığında seçili mod.</li>
  <li><b>Alt ağ maskelerini koru:</b> <code>255.255.255.0</code>, <code>0.0.0.255</code> gibi değerler IP sayılmaz; ağ analizi için açık kalır.</li>
  <li><b>Kalan risk anahtar kelimeleri:</b> Hiç maskeleme yapılmayan satırda bu regex eşleşirse kullanıcı uyarılır. Boş bırakılırsa uyarı yapılmaz.</li>
  <li><b>En büyük girdi (KB):</b> Tek seferde maskelenebilecek metin boyutu. Çok büyük değerler PHP bellek ve süre sınırlarına takılabilir.</li>
  <li><b>Kullanım istatistiği tut:</b> Yalnızca sayısal istatistik (karakter, eşleşme sayısı, süre) kaydeder; içerik asla saklanmaz. Sohbetteki her mesaj da bir maskeleme olarak sayılır.</li>
</ul>
<p>Sohbetle ilgili ayarlar aynı ekranın altındaki <b>Sohbet (yapay zekâ)</b> kutusundadır; ayrıntılar bir sonraki bölümde.</p>

<?php g_section('sohbet', 'Sohbet (yapay zekâ)'); ?>
<p>Sohbet ekranı, Maskink'i kullanıcı ile yapay zekâ servisi arasına yerleştirir. Tarayıcı bu servisle hiç doğrudan konuşmaz; tüm istekler sunucudaki <code>api.php</code> üzerinden geçer.</p>
<ol>
  <li>Tarayıcı yeni mesajı <b>gerçek hâliyle</b>, önceki mesajları <b>maskelenmiş hâliyle</b> ve sohbetin eşleme tablosunu sunucuya gönderir (HTTPS/oturum + CSRF korumalı).</li>
  <li>Sunucu yeni mesajı motordan geçirir (sohbet eşlemesiyle birlikte). Modele giden istek: <b>sistem istemi + maskelenmiş geçmiş + maskelenmiş yeni mesaj</b>. Gerçek değerler yapay zekâ servisine gitmez.</li>
  <li>Model yanıtı akış (SSE) olarak gelir ve <b>maskelenmiş hâliyle</b> tarayıcıya iletilir.</li>
  <li>Geri çevirme tarayıcıda, sohbetin eşleme tablosuyla yapılır. Tam token biçimi yanında, modelin sık yaptığı küçük bozulmalar da (köşeli işaretlerin düşmesi, <code>&amp;lt;IP_1&amp;gt;</code> gibi HTML kaçışı) tanınır. Eşlemede olmayan tokenlar kullanıcıya uyarı olarak gösterilir.</li>
</ol>

<h3>Ayarlar → Genel ayarlar → Sohbet</h3>
<ul>
  <li><b>Varsayılan model:</b> Yeni sohbetlerde seçili gelen model. Liste bağlantılardan canlı okunur; kullanıcı sohbet ekranında başka model seçebilir.</li>
  <li><b>Sıcaklık:</b> 0–2 arası; boş bırakılırsa modelin kendi varsayılanı kullanılır. Teknik destek ve komut üretimi için düşük değerler (0–0,3) daha tutarlıdır.</li>
  <li><b>Sistem istemi:</b> Her isteğin başına eklenir. <code>{EXAMPLE}</code> geçerli token şablonuyla üretilmiş örnek tokenlarla (ör. <code>&lt;IP_1&gt;, &lt;HOST_1&gt;</code>), <code>{CATEGORIES}</code> etkin kategori listesiyle doldurulur. <b>Varsayılana döndür</b> ile ilk hâline getirilebilir.</li>
</ul>
<?php g_note('Sistem istemindeki “yer tutucuları birebir aynı yaz, yeni yer tutucu uydurma, gerçek değeri tahmin etme” kurallarını silmeyin. Bu kurallar olmadan model tokenları değiştirebilir ve yanıt geri çevrilemez.', 'warn'); ?>

<h3>Ekran görüntüleri</h3>
<p>Kullanıcı sohbete ekran görüntüsü ekleyebilir. Görselin hassas kısımları <b>tarayıcıda</b> kapatılır; sunucu görseli okumaz, değiştirmez ve saklamaz.</p>
<ol>
  <li>Görseldeki yazılar tarayıcıda OCR ile okunur (Tesseract.js, Türkçe + İngilizce; dosyalar <code>assets/vendor/tesseract/</code> içinde, internet gerekmez). Okuma için görsel hiçbir yere gönderilmez. Görsel önce hazırlanır (küçükse 2–3 kat büyütme, gri ton, kontrast germe, çoğunlukla koyuysa ters çevirme) ve iki geçişte okunur: sayfa düzeni (PSM 3) ve dağınık yazı (PSM 11). İkinci geçiş yalnızca ilkinin bulamadığı ya da zayıf okuduğu kelimeleri ekler.</li>
  <li><b>Tablo sütunları:</b> Başlığı <i>Kullanıcı, User, Username, Hesap, Account, Owner, Sunucu, Server, Host, Bilgisayar, Ad Soyad, Display Name…</i> olan sütunlardaki hücreler, kural bağlamı olmasa da ilgili kategoride maskelenir. Sütun sınırını kesen bir satır (düz cümle) ya da satır aralığındaki büyük boşluk tablonun bittiğini gösterir.</li>
  <li>Okunan metin <code>mask</code> ucuyla, sohbetin eşlemesi kullanılarak maskelenir; maskelenen her değerin kelime kutularının üstü tamamen örtülür ve tokenı yazılır.</li>
  <li><b>Elle düzeltme:</b> Kullanıcı bir kelimeyi seçip kategori verebilir (<code>hints</code>: değer her yerde o kategoride maskelenir) ya da kapatılmış bir alana sağ tıklayıp maskesini kaldırabilir (<code>allow</code>: değer her yerde açık kalır). İkisi de motorun seçenekleridir ve sunucuya da gider; mesaj metnine ve okunan metne aynı şekilde uygulanır. Tam okunamayan ya da kimlik gibi görünen ama maskelenmemiş kelimeler turuncu çerçeveyle işaretlenir.</li>
  <li>Gönderimden hemen önce okunan metin, mesajın yeni değerleri de dahil güncel eşlemeyle <b>yeniden maskelenir</b> ve görsel son hâliyle üretilir (en uzun kenar 2000 px, JPEG). Böylece mesajda maskelenen bir değer görselde de kapanır.</li>
  <li>Orijinal görsel de kullanıcının tarayıcısında saklanır (yalnızca orada; sohbetteki <b>Yapay zekânın gördüğü</b> geçişiyle karşılaştırılır). Sunucuya gelen görsel her zaman üstü kapatılmış olandır.</li>
  <li>Sunucu okunan metni aynı eşleme, <code>hints</code> ve <code>allow</code> ile bir kez daha maskeler ve modele <b>mesaj + okunan metnin maskeli hâli + üstü kapatılmış görsel</b> gönderir. Model görsel desteklemiyorsa görsel gönderilmez, yalnızca metin gider. Geçmişteki görsellerden en fazla 6 tanesi modele yeniden gönderilir.</li>
</ol>
<p>Hangi modellerin görsel kabul ettiği <b>Yapay zekâ bağlantıları</b> ekranında belirlenir (Ollama bunu kendisi bildirir).</p>
<?php g_note('OCR kusursuz değildir: okunamayan yazılar kapatılmaz, yazı olmayan hassas içerik (yüz, imza, QR kod, logo) hiç algılanmaz. Kullanıcılar gönderimden önce görseli pencerede kontrol etmelidir. Görsel gönderimini tamamen engellemek isterseniz bağlantılardaki <b>Görsel destekleyen modeller</b> alanını boşaltın ve Ollama modellerini hariç tutun; o zaman yalnızca maskelenmiş metin gider.', 'warn'); ?>

<p>Modellerin hangi adreslerden geldiği bir sonraki bölümde, <b>Yapay zekâ bağlantıları</b> ekranında yönetilir.</p>

<h3>Saklama ve gizlilik</h3>
<ul>
  <li>Sohbetler ve eşleme tabloları yalnızca kullanıcının tarayıcısında (<code>localStorage</code>) durur; sunucuda ya da veritabanında kopyası yoktur. Yönetici kullanıcıların sohbetlerini göremez.</li>
  <li>Mesajlar sunucuda yalnızca istek süresince bellekte tutulur. Veritabanına yalnızca (açıksa) sayısal kullanım istatistiği yazılır.</li>
  <li>Ağ geçidi yalnızca maskelenmiş metni görür. Ağ geçidinin kendi tarafında istek kaydı tutup tutmadığı bu uygulamanın denetiminde değildir.</li>
  <li><b>Maskeleme ekranı için de giriş zorunlu</b> ayarı açıksa sohbet de yalnızca yönetici oturumuyla kullanılabilir.</li>
</ul>

<?php g_section('baglanti', 'Yapay zekâ bağlantıları'); ?>
<p><b>Ayarlar → Yapay zekâ bağlantıları</b> ekranı, sohbetin kullandığı uç noktaları listeler. Her bağlantı bir adres, isteğe bağlı bir API anahtarı ve bir model listesinden oluşur. <b>Etkin</b> bağlantıların modelleri sohbet ekranındaki model listesinde, bağlantı adı altında gruplanarak görünür.</p>
<ul>
  <li><b>+ Yeni bağlantı:</b> Boş bir form açar.</li>
  <li><b>Düzenle:</b> Bağlantının formunu açar; değişiklikler <b>Kaydet</b> ile uygulanır.</li>
  <li><b>Bağlantıyı dene:</b> Formdaki (henüz kaydedilmemiş) bilgilerle model listesini çeker ve bulunan modelleri gösterir. Elle model listesinde ilk modele çok kısa bir deneme isteği gönderilir.</li>
  <li><b>↑ / ↓:</b> Bağlantının sırasını değiştirir; hemen kaydedilir. Sohbet ekranındaki model listesinde gruplar bu sırayla görünür.</li>
  <li><b>Sil:</b> Bağlantıyı kaldırır. Bu bağlantının modellerini kullanan sohbetler bir sonraki mesajda başka model seçmek zorunda kalır; varsayılan model bu bağlantıdaysa <b>Genel ayarlar</b>dan yenisini seçin.</li>
</ul>

<h3>Form alanları</h3>
<div class="mk-table"><table>
  <thead><tr><th>Alan</th><th>Açıklama</th></tr></thead>
  <tbody>
    <tr><td>Ad</td><td>Model listesinde grup başlığı. Bağlantının kimliği (ör. <code>vllm</code>) ilk kayıtta addan üretilir ve değişmez; model kimlikleri <code>&lt;kimlik&gt;:&lt;model&gt;</code> biçimindedir (ör. <code>vllm:GPTOSS-120B</code>).</td></tr>
    <tr><td>Tür</td><td>Adreslerin nasıl kurulacağını belirler (aşağıdaki tablo).</td></tr>
    <tr><td>Temel adres</td><td>Uç noktanın kökü. Form, model listesi ve sohbet için kullanılacak tam adresleri altında gösterir.</td></tr>
    <tr><td>API anahtarı</td><td><code>Authorization: Bearer</code> başlığıyla gönderilir. Kaydedildikten sonra ekrana bir daha gönderilmez, yalnızca son 4 karakteri görünür. Düzenlerken boş bırakılırsa mevcut anahtar korunur; <b>Kayıtlı anahtarı sil</b> ile kaldırılır.</td></tr>
    <tr><td>Zaman aşımı</td><td>Bir yanıt için beklenecek en uzun süre (saniye).</td></tr>
    <tr><td>Model listesi</td><td><b>Otomatik:</b> uç noktanın model listesinden okunur (10 dakika önbelleklenir; sohbet ekranındaki yenile düğmesi önbelleği atlar). Gömme (embedding) modelleri gösterilmez; <b>Hariç tutulacak modeller</b>e ad ya da <code>/regex/i</code> yazarak başka modelleri de gizleyebilirsiniz. <b>Elle:</b> model listesi okunamayan ya da yalnızca belirli modellerin görünmesini istediğiniz uç noktalar için; her satıra bir model adı. Modeller yazıldığı sırayla listelenir.</td></tr>
    <tr><td>Model sıralaması</td><td>Yalnızca otomatik listede. Önce görünecek modeller, yukarıdan aşağıya; her satıra bir ad ya da <code>/regex/i</code> (ör. <code>/qwen/i</code> tüm Qwen modellerini bir arada öne alır). Yazılmayan modeller uç noktanın verdiği sırayla sona eklenir; böylece uç noktaya yeni bir model eklendiğinde listeden kaybolmaz. <b>Bağlantıyı dene</b> sonucundaki <b>Bu listeyi sıralama alanına aktar</b> bağlantısı mevcut listeyi alana yazar; satırların yerini değiştirip kaydetmeniz yeterlidir. Sohbet ekranında ilk model, kullanıcının daha önce seçtiği ya da varsayılan model yoksa seçili gelir.</td></tr>
    <tr><td>Görsel destekleyen modeller</td><td>Ekran görüntüsü kabul eden modeller; her satıra bir ad ya da <code>/regex/i</code>. Bu modellere üstü kapatılmış görsel de gönderilir, diğerlerine yalnızca görselden okunan metin gider. Ollama görsel desteğini (<code>vision</code>) kendisi bildirir. Bir modelin görsel kabul edip etmediğinden emin değilseniz yazmayın: desteklemeyen modele görsel gönderilirse “not a multimodal model” hatası alınır.</td></tr>
    <tr><td>Etkin</td><td>Kapalı bağlantının modelleri listelenmez ve kullanılamaz.</td></tr>
    <tr><td>TLS sertifikasını doğrula</td><td>Yalnızca kurum içi sertifika sorunlarında geçici olarak kapatın.</td></tr>
  </tbody>
</table></div>

<h3>Bağlantı türleri</h3>
<div class="mk-table"><table>
  <thead><tr><th>Tür</th><th>Model listesi</th><th>Sohbet isteği</th></tr></thead>
  <tbody>
    <tr><td>vLLM ağ geçidi</td><td><code>{adres}/v1/models</code></td><td><code>{adres}/&lt;model-yolu&gt;/v1/chat/completions</code> – model yolu addan üretilir: <code>Qwen3.8-27B</code> → <code>qwen3-8-27b</code></td></tr>
    <tr><td>Ollama</td><td><code>{adres}/models</code>, olmazsa <code>{adres}/api/tags</code></td><td><code>{adres}/v1/chat/completions</code></td></tr>
    <tr><td>OpenAI uyumlu</td><td><code>{adres}/models</code></td><td><code>{adres}/chat/completions</code> – tek bir vLLM modeli, OpenAI, LM Studio, LiteLLM vb.</td></tr>
  </tbody>
</table></div>

<h3>Varsayılan bağlantılar</h3>
<p>Siz kendi bağlantılarınızı kaydedene kadar kullanılan bağlantılar <code>src/Seeds.php</code> → <code>llmConnections()</code> içinde tanımlıdır. Bu ekrandan ortamınıza göre düzenleyin; değişiklikleriniz veritabanında saklanır.</p>
<?php g_note('Bağlantılar ve API anahtarları veritabanında (<code>mk_settings.llm_connections</code>) saklanır ve <b>Yedekle / dışa aktar</b> dosyasına dahil edilmez. Hiç değişiklik yapılmadıysa yukarıdaki varsayılanlar kullanılır; tüm bağlantıları silerseniz sohbet çalışmaz.', 'info'); ?>
<?php g_note('Yapay zekâya her zaman yalnızca maskelenmiş metin gider; bu, hangi bağlantıyı eklerseniz ekleyin geçerlidir. Yine de kurum dışı (internet üzerindeki) bir hizmet eklemeden önce veri politikanızı kontrol edin: maskelenmiş metin de iş bağlamını içerir.', 'warn'); ?>

<?php g_section('yedek', 'Yedekle / geri yükle'); ?>
<ul>
  <li><b>Dışa aktar:</b> Tüm kuralları, kategorileri ve düzenlenebilir ayarları JSON olarak indirir. Yönetici parolası ve kuruluma özgü gizli anahtar dışa aktarılmaz.</li>
  <li><b>İçe aktar:</b> Yerleşik kurallar anahtarlarıyla (bkey) eşleşip güncellenir; özel kurallar ad + tür + kategoriye göre eşleşir, yoksa eklenir. Aynı dosyayı iki kez içe aktarmak kural çoğaltmaz. <b>Mevcut özel kuralları önce sil</b> seçilirse özel kurallarınız dosyadakilerle değiştirilir.</li>
  <li><b>Eksik varsayılanları ekle:</b> Yeni sürümle gelen yerleşik kuralları ekler; mevcut kurallara dokunmaz.</li>
  <li><b>Yerleşikleri sıfırla:</b> Tüm yerleşik kuralları ve kategorileri ilk hâline döndürür. Özel kurallarınız korunur, yerleşik kurallarda yaptığınız değişiklikler kaybolur.</li>
</ul>
<?php g_note('Büyük bir değişiklikten önce (ör. yerleşikleri sıfırlamadan ya da başka kurulumdan içe aktarmadan önce) mutlaka dışa aktararak yedek alın.', 'tip'); ?>

<?php g_section('guvenlik', 'Güvenlik ve değişiklik günlüğü'); ?>
<ul>
  <li><b>Yönetici parolasını değiştir:</b> Mevcut parola ve en az 8 karakterli yeni parola istenir.</li>
  <li><b>Maskeleme ekranı için de giriş zorunlu:</b> Açıksa maskeleme yalnızca yönetici oturumuyla yapılabilir.</li>
  <li><b>Oturum zaman aşımı:</b> Dakika cinsinden, en az 5.</li>
  <li><b>Değişiklik günlüğü:</b> Kural, kategori ve ayar değişiklikleri ile başarılı/başarısız giriş denemeleri zaman ve IP adresiyle kaydedilir (son 500 kayıt görüntülenir).</li>
</ul>
<p>Tüm yönetim istekleri oturum ve CSRF belirteci ile korunur. Maskelenen metinler sunucuda saklanmaz; eşleme tablosu yalnızca kullanıcının tarayıcı sayfasında durur.</p>

<?php g_section('sorun', 'Sorun giderme'); ?>
<h3>Bir değer maskelenmiyor</h3>
<ol>
  <li>Satırı uygun bir kuralın (ör. parola için <i>kv_password</i>) Canlı test alanına yapıştırın.</li>
  <li><b>Reddedilen adaylar</b>da değer görünüyorsa nedenine göre düzeltin: tutma listesinden çıkarın, doğrulayıcıyı ya da uzunluk sınırını gevşetin, "Değişken referanslarını atla" seçeneğini gözden geçirin.</li>
  <li>Hiç aday yoksa hiçbir kural değeri yakalamıyordur: anahtar=değer kuralına yeni anahtar ekleyin, yeni bir regex kuralı yazın ya da terimi özel sözlüğe ekleyin.</li>
  <li>FQDN tanınmıyorsa büyük olasılıkla uzantısı <b>TLD listesi</b>nde yoktur.</li>
  <li>Kural yalnızca bir modda çalışıyorsa test modunu değiştirerek deneyin.</li>
</ol>
<h3>Gereksiz şeyler maskeleniyor</h3>
<ul>
  <li>Değer her yerde açık kalmalıysa <b>Asla maskelenmeyecek değerler</b>e, alan adıysa <b>Maskelenmeyecek alan adları</b>na, IP aralığıysa <b>Maskelenmeyecek IPv4 aralıkları</b>na ekleyin.</li>
  <li>Belirli bir kural yanlış pozitif üretiyorsa (eşleme tablosundaki "Tespit eden kural" sütunundan bulun) hariç tutma regex'i ekleyin, önceliğini düşürün ya da kuralı kapatın.</li>
  <li><code>logger.info</code> gibi kod ifadeleri ad.soyad sanılıyorsa <b>Kod belirteçleri</b>ne, dosya adları kullanıcı sanılıyorsa <b>ad.soyad hariç parçalar</b>a ekleyin.</li>
</ul>
<h3>Kurallar listesinde "hatalı" etiketi</h3>
<p>Kuralın regex'i derlenemiyor demektir; motor bu kuralı atlar, diğerleri çalışmaya devam eder. Etiketin üzerine gelince hata mesajı görünür. Genellikle bir listede yapılan değişiklik (ör. TLD listesine özel karakter eklenmesi) şablon kullanan kuralları bozar.</p>
<h3>Sohbet: model listesi alınamıyor</h3>
<ul>
  <li><b>Yapay zekâ bağlantıları</b> ekranında ilgili bağlantıyı açıp <b>Bağlantıyı dene</b>ye basın; hata mesajı sorunu gösterir. Sohbet ekranı da ulaşılamayan bağlantıları sarı bir uyarıyla bildirir.</li>
  <li>Sunucudan uç noktaya erişimi kontrol edin: model listesi adresi (ör. <code>{adres}/v1/models</code>) sunucunun kendisinden açılabilmeli (vekil sunucu, güvenlik duvarı).</li>
  <li>Hata TLS/sertifika ile ilgiliyse PHP'nin <code>curl.cainfo</code> ayarını kontrol edin; son çare olarak bağlantıda <b>TLS sertifikasını doğrula</b>yı kapatın.</li>
  <li>Bir model “HTTP 302/401” ya da “yetki gerekiyor” diyorsa API anahtarı istiyordur: anahtarı ilgili bağlantıya girin ve modeli anahtarsız bağlantıda hariç tutun.</li>
</ul>
<h3>Sohbet: yanıt tek seferde geliyor, akmıyor</h3>
<p>Araya giren bir sıkıştırma ya da tampon katmanı (ör. Apache <code>mod_deflate</code>, ters vekil sunucu) akışı bekletiyordur. <code>text/event-stream</code> yanıtları için sıkıştırmayı ve tamponlamayı kapatın; uygulama <code>X-Accel-Buffering: no</code> başlığını zaten gönderir.</p>
<h3>Sohbet: yanıtta geri çevrilmemiş tokenlar</h3>
<p>Model tokenı bozmuş ya da uydurmuştur. Sistem istemindeki token kurallarının yerinde olduğunu kontrol edin; sorun belirli bir modelde sürekli yaşanıyorsa varsayılan model olarak başka bir modeli seçin. Token şablonunu <code>&lt;{CAT}_{n}&gt;</code> gibi kısa ve belirgin tutmak modellerin tokenı korumasını kolaylaştırır.</p>
<h3>Sohbet: ekran görüntüsü okunmuyor</h3>
<ul>
  <li>Pencere “OCR hazırlanıyor” durumunda kalıyorsa tarayıcının <code>assets/vendor/tesseract/</code> altındaki dosyaları alabildiğini kontrol edin (ör. <code>…/lang/tur.traineddata</code> adresi açılmalı). Dosyalar yaklaşık 13 MB'tır; ilk kullanımdan sonra tarayıcıda önbelleklenir.</li>
  <li>Yazılar eksik okunuyorsa görsel çok küçük ya da bulanık olabilir; ekranı yakınlaştırıp yeniden görüntü alın. Okunamayan yerleri kullanıcı elle kapatmalıdır.</li>
  <li>Model “not a multimodal model” hatası veriyorsa bağlantıdaki <b>Görsel destekleyen modeller</b> listesinden o modeli çıkarın.</li>
</ul>
<h3>Maskeleme yavaş ya da zaman aşımına uğruyor</h3>
<p>Genel bakışta PCRE JIT'in açık olduğunu kontrol edin, <b>En büyük girdi</b> değerini düşürün ve <code>.*</code> gibi geniş, iç içe tekrar içeren özel regex'leri sadeleştirin.</p>

<?php g_section('teknik', 'Teknik notlar'); ?>
<ul>
  <li><b>Kurulum yoktur.</b> Klasör web sunucusuna kopyalandığında ilk istekte veritabanı, tablolar ve varsayılan kurallar otomatik oluşturulur.</li>
  <li><b>Veritabanı bağlantısı:</b> <code>config.php</code> (sunucu, port, veritabanı adı, kullanıcı, parola). Bu dosya web üzerinden erişime kapalıdır.</li>
  <li><b>Tablolar:</b> <code>mk_settings</code> (ayarlar), <code>mk_categories</code>, <code>mk_rules</code>, <code>mk_audit</code> (değişiklik günlüğü), <code>mk_usage</code> (sayısal istatistik).</li>
  <li><b>Yerleşik kurallar</b> <code>src/Seeds.php</code> dosyasında tanımlıdır; motor <code>src/Engine.php</code> içindedir. Yeni sürüme geçtikten sonra <b>Yedekle → Eksik varsayılanları ekle</b> çalıştırın.</li>
  <li><b>Arayüz</b> CDN kullanmaz; Tailwind yerel <code>assets/js/tailwind.js</code> dosyasından yüklenir, internet bağlantısı gerekmez.</li>
  <li><b>Erişim:</b> <code>src/</code> ve <code>partials/</code> klasörleri web erişimine kapalıdır.</li>
  <li><b>Sohbet bileşenleri:</b> Sayfa <code>chat.php</code>, istemci <code>assets/js/chat.js</code>, ekran görüntüsü okuma ve kapatma <code>assets/js/vision.js</code> (Tesseract.js 5.1.1, <code>assets/vendor/tesseract/</code>), bağlantı istemcisi <code>src/Llm.php</code> (varsayılan bağlantılar <code>src/Seeds.php</code> → <code>llmConnections()</code>); uç noktalar <code>api.php?action=chat.models</code>, <code>chat.send</code> ve yönetim için <code>llm.list</code>, <code>llm.save</code>, <code>llm.test</code>, <code>llm.reorder</code>, <code>llm.delete</code>. Uygulama kök adresi doğrudan Sohbet ekranını açar.</li>
  <li><b>Dil:</b> Arayüz varsayılan olarak İngilizcedir; üst çubuktaki dil düğmesi Türkçeye geçirir (tercih <code>mki_lang</code> çerezinde tutulur). Çeviriler <code>lang/tr/*.php</code> dosyalarındadır. Yerleşik kural ve kategori adları veritabanında İngilizce saklanır ve ekranda seçili dile çevrilir; düzenleme formunda saklanan (İngilizce) değer görünür.</li>
  <li><b>Veritabanı ve oturum:</b> Maskink kendi <code>maskink</code> veritabanını, <code>MASKINKSESS</code> oturum çerezini ve <code>mki-</code> önekli tarayıcı depolamasını kullanır; aynı sunucudaki başka kurulumlardan bağımsızdır.</li>
</ul>

<?php g_close(); ?>
