<?php
require_once __DIR__ . '/../config/database.php';

echo "Running migration...\n";

// Check if articles already exist
$count = $pdo->query("SELECT COUNT(*) FROM education_articles")->fetchColumn();
if ($count > 0) {
  echo "Articles already exist ($count found).\n";
} else {
  // Seed initial 5 articles
  $articles = [
    [
      'id' => 1,
      'title' => 'Pengertian Haid',
      'slug' => 'pengertian-haid',
      'category' => 'Dasar Fikih',
      'icon' => '🌷',
      'description' => 'Mengenal pengertian haid dan karakteristiknya menurut Mazhab Syafi’i.',
      'content' => "<h4>1. Pengertian Haid</h4>
<p>Secara bahasa, haid berarti mengalir. Sedangkan menurut istilah fikih, haid adalah darah alami yang keluar dari rahim perempuan pada waktu tertentu, bukan disebabkan oleh proses melahirkan.</p>
<p>Haid merupakan keadaan alami yang dialami perempuan dan memiliki ketentuan khusus dalam syariat Islam. Ketentuan tersebut berkaitan dengan pelaksanaan ibadah seperti salat, puasa, membaca Al-Qur’an, dan bersuci.</p>

<h4>2. Haid Menurut Mazhab Syafi’i</h4>
<p>Dalam Mazhab Syafi’i, darah haid memiliki ketentuan waktu tertentu. Ketentuan ini digunakan sebagai pedoman untuk membedakan darah haid dengan darah istihadhah.</p>
<ul>
  <li>Minimal haid adalah 24 jam.</li>
  <li>Umumnya haid berlangsung selama 6–7 hari.</li>
  <li>Maksimal haid adalah 15 hari 15 malam.</li>
</ul>
<p>Ketentuan tersebut merupakan hasil penelitian (istiqra’) terhadap kebiasaan perempuan sebagaimana dibahas dalam kitab-kitab fikih Mazhab Syafi’i.</p>

<h4>3. Karakteristik Darah Haid</h4>
<p>Dalam pembahasan fikih, darah yang dihukumi haid dapat memiliki beberapa warna. Warna tersebut antara lain hitam, merah, merah kekuningan, kuning, dan keruh kecokelatan.</p>
<p>Namun, warna darah saja tidak cukup untuk menentukan hukumnya. Waktu keluarnya darah, masa suci sebelumnya, dan kebiasaan haid juga perlu diperhatikan.</p>

<h4>4. Perbedaan Haid dan Istihadhah</h4>
<p>Haid dan istihadhah merupakan dua keadaan yang memiliki ketentuan ibadah berbeda.</p>
<ul>
  <li>Haid: perempuan tidak melaksanakan salat dan tidak berpuasa. Puasa Ramadan yang ditinggalkan wajib diqadha.</li>
  <li>Istihadhah: perempuan tetap wajib melaksanakan salat dan puasa dengan mengikuti ketentuan bersuci yang berlaku.</li>
</ul>

<h4>5. Hal yang Perlu Diperhatikan</h4>
<ul>
  <li>Tidak semua darah yang keluar otomatis dihukumi sebagai haid.</li>
  <li>Darah yang keluar kurang dari 24 jam memiliki ketentuan khusus.</li>
  <li>Perdarahan lebih dari 15 hari perlu ditinjau berdasarkan ketentuan istihadhah.</li>
  <li>Kebiasaan haid sebelumnya dapat berpengaruh dalam menentukan hukum darah.</li>
</ul>

<div class=\"alert alert-light border mt-4\">
  <strong>Catatan Penting</strong>
  <p class=\"mb-0 mt-2\">Materi ini merupakan pengenalan fikih haid. Penentuan hukum pada kasus pribadi memerlukan pemeriksaan waktu keluarnya darah, kebiasaan haid, serta ketentuan fikih lainnya.</p>
</div>",
      'references' => "Kitab Fath al-Qarib, pembahasan haid.\nKitab Minhaj al-Talibin karya Imam An-Nawawi.\nKajian fikih Mazhab Syafi’i mengenai ketentuan haid."
    ],
    [
      'id' => 2,
      'title' => 'Masa Minimum dan Maksimum Haid',
      'slug' => 'masa-minimum-dan-maksimum-haid',
      'category' => 'Ketentuan Haid',
      'icon' => '📖',
      'description' => 'Mengenal batas waktu haid menurut Mazhab Syafi’i.',
      'content' => "<h4>1. Batas Minimal Haid</h4>
<p>Menurut Mazhab Syafi’i, masa minimal haid adalah 24 jam. Durasi tersebut dapat terjadi secara terus-menerus atau terputus-putus dengan ketentuan akumulasi waktu keluarnya darah mencapai 24 jam dalam rentang maksimal 15 hari.</p>

<h4>2. Masa Umum Haid</h4>
<p>Kebanyakan perempuan mengalami haid selama 6–7 hari. Namun, durasi tersebut bukan kewajiban atau ketentuan yang harus dialami semua perempuan.</p>

<h4>3. Batas Maksimal Haid</h4>
<p>Batas maksimal haid menurut Mazhab Syafi’i adalah 15 hari 15 malam. Apabila darah keluar melebihi batas tersebut, maka diperlukan peninjauan hukum istihadhah.</p>

<h4>4. Contoh Perhitungan</h4>
<div class=\"alert alert-light border mt-3\">
  <strong>Contoh pertama</strong>
  <p class=\"mb-0 mt-2\">Seorang perempuan mengalami darah haid selama 6 hari berturut-turut. Durasi tersebut berada dalam rentang minimal dan maksimal haid.</p>
</div>
<div class=\"alert alert-light border mt-3\">
  <strong>Contoh kedua</strong>
  <p class=\"mb-0 mt-2\">Seorang perempuan mengalami darah yang keluar secara terputus-putus. Dalam kondisi ini, waktu keluarnya darah perlu dihitung secara akumulatif dan diperiksa dalam rentang maksimal 15 hari.</p>
</div>
<div class=\"alert alert-light border mt-3\">
  <strong>Contoh ketiga</strong>
  <p class=\"mb-0 mt-2\">Seorang perempuan mengalami perdarahan selama 18 hari. Kondisi ini tidak dapat langsung ditentukan dengan menganggap 15 hari pertama sebagai haid dan sisanya sebagai istihadhah. Perlu diperiksa kebiasaan haid dan ketentuan penggolongan darah.</p>
</div>

<h4>5. Kesimpulan</h4>
<ul>
  <li>Minimal haid: 24 jam.</li>
  <li>Umumnya haid: 6–7 hari.</li>
  <li>Maksimal haid: 15 hari 15 malam.</li>
  <li>Kasus perdarahan tidak teratur memerlukan peninjauan fikih lebih lanjut.</li>
</ul>

<div class=\"alert alert-light border mt-4\">
  <strong>Catatan Penting</strong>
  <p class=\"mb-0 mt-2\">Perhitungan haid tidak hanya berdasarkan jumlah hari pada kalender. Waktu keluarnya darah dan masa terputusnya darah perlu diperhatikan.</p>
</div>",
      'references' => "Kitab Fath al-Qarib, bab haid.\nKitab Kifayat al-Akhyar karya Imam Taqiyuddin Al-Hishni.\nKitab Al-Majmu’ karya Imam An-Nawawi."
    ],
    [
      'id' => 3,
      'title' => 'Masa Minimum Suci',
      'slug' => 'masa-minimum-suci',
      'category' => 'Ketentuan Suci',
      'icon' => '🌿',
      'description' => 'Memahami jarak minimum antara dua masa haid.',
      'content' => "<h4>1. Pengertian Masa Suci</h4>
<p>Masa suci adalah periode ketika perempuan tidak sedang mengalami haid atau nifas. Masa ini menjadi waktu bagi perempuan untuk kembali melaksanakan ibadah yang sebelumnya tidak dapat dilakukan ketika haid.</p>

<h4>2. Minimal Masa Suci</h4>
<p>Menurut Mazhab Syafi’i, masa suci minimal antara dua haid adalah 15 hari 15 malam. Ketentuan ini menjadi salah satu pedoman dalam membedakan periode haid yang satu dengan periode berikutnya.</p>

<h4>3. Maksimal Masa Suci</h4>
<p>Masa suci tidak memiliki batas maksimal. Seorang perempuan dapat mengalami masa suci selama beberapa minggu, bulan, bahkan lebih lama.</p>

<h4>4. Tanda Berakhirnya Haid</h4>
<ul>
  <li>Al-Qashshah Al-Baidha: cairan putih yang menjadi tanda suci bagi perempuan yang mengenalinya.</li>
  <li>Al-Jufuf: keadaan kering dan bersih dari bekas darah haid.</li>
</ul>

<h4>5. Kewajiban Setelah Suci</h4>
<p>Ketika haid telah berakhir dan tanda suci telah diketahui, perempuan wajib melaksanakan mandi wajib sebelum kembali menjalankan ibadah yang mensyaratkan kesucian dari hadas besar.</p>

<div class=\"alert alert-light border mt-3\">
  <strong>Contoh sederhana</strong>
  <p class=\"mb-0 mt-2\">Seorang perempuan telah selesai haid dan mengalami masa suci. Ketika darah kembali keluar sebelum terpenuhi masa suci minimal 15 hari, status darah tersebut perlu ditinjau berdasarkan ketentuan fikih, bukan otomatis dianggap haid baru.</p>
</div>

<div class=\"alert alert-light border mt-4\">
  <strong>Catatan Penting</strong>
  <p class=\"mb-0 mt-2\">Masa suci minimal 15 hari dihitung berdasarkan ketentuan fikih. Perhitungan kasus darah yang keluar kembali membutuhkan perhatian terhadap urutan waktu dan kondisi darah.</p>
</div>",
      'references' => "Kitab Fath al-Qarib, pembahasan masa suci.\nKitab Al-Majmu’ karya Imam An-Nawawi.\nKajian fikih Mazhab Syafi’i tentang masa suci."
    ],
    [
      'id' => 4,
      'title' => 'Istihadhah',
      'slug' => 'istihadhah',
      'category' => 'Jenis Darah',
      'icon' => '🩷',
      'description' => 'Mengenal darah istihadhah dan perbedaannya dengan haid.',
      'content' => "<h4>1. Pengertian Istihadhah</h4>
<p>Istihadhah adalah darah yang keluar di luar ketentuan haid dan nifas. Dalam fikih, darah istihadhah termasuk darah yang tidak menghalangi perempuan untuk melaksanakan ibadah.</p>

<h4>2. Kondisi Istihadhah</h4>
<p>Istihadhah dapat terjadi pada perempuan yang mengalami perdarahan melewati batas maksimal haid atau memiliki pola darah yang memerlukan penentuan berdasarkan kebiasaan dan sifat darah.</p>

<h4>3. Jenis Kondisi Perempuan Mustahadhah</h4>
<ul>
  <li>Mubtada’ah: perempuan yang baru pertama kali mengalami haid dan mengalami perdarahan berkepanjangan.</li>
  <li>Mu’tadah: perempuan yang telah memiliki kebiasaan haid sebelumnya.</li>
  <li>Mumayyizah: perempuan yang dapat membedakan sifat darah kuat dan lemah berdasarkan ketentuan fikih.</li>
  <li>Mutahayyirah: perempuan yang mengalami kesulitan menentukan kebiasaan atau pembedaan darah.</li>
</ul>

<h4>4. Ketentuan Ibadah</h4>
<p>Perempuan yang mengalami istihadhah tetap wajib melaksanakan salat dan puasa. Ia juga tetap dapat membaca Al-Qur’an dan menjalankan ibadah lainnya sesuai ketentuan yang berlaku.</p>

<h4>5. Cara Bersuci</h4>
<ul>
  <li>Membersihkan bagian tubuh yang terkena darah.</li>
  <li>Menggunakan pembalut atau penahan darah agar tidak menyebar.</li>
  <li>Berwudu setelah masuk waktu salat fardu menurut ketentuan perempuan yang mengalami hadas terus-menerus.</li>
  <li>Segera melaksanakan salat setelah bersuci sesuai ketentuan fikih.</p>
</ul>

<h4>6. Hal yang Perlu Diperhatikan</h4>
<p>Penentuan istihadhah tidak cukup hanya dengan menghitung jumlah hari perdarahan. Kebiasaan haid, kemampuan membedakan sifat darah, dan urutan waktu keluarnya darah harus diperhatikan.</p>

<div class=\"alert alert-light border mt-4\">
  <strong>Catatan Penting</strong>
  <p class=\"mb-0 mt-2\">Pembahasan istihadhah memiliki rincian hukum yang cukup luas. Setiap kondisi perlu diperiksa secara khusus. Perdarahan yang tidak biasa atau berkepanjangan juga sebaiknya diperiksakan kepada tenaga kesehatan.</p>
</div>",
      'references' => "Kitab Fath al-Qarib, bab istihadhah.\nKitab Al-Majmu’ karya Imam An-Nawawi.\nKitab Kifayat al-Akhyar karya Imam Taqiyuddin Al-Hishni."
    ],
    [
      'id' => 5,
      'title' => 'Mencatat Siklus Menstruasi',
      'slug' => 'mencatat-siklus-menstruasi',
      'category' => 'Kesehatan',
      'icon' => '📝',
      'description' => 'Pentingnya pencatatan untuk mengenali pola siklus.',
      'content' => "<h4>1. Pengertian Pencatatan Siklus</h4>
<p>Pencatatan siklus menstruasi merupakan kegiatan mendokumentasikan tanggal mulai, tanggal selesai, dan pola perdarahan yang dialami perempuan. Catatan ini dapat membantu mengenali perubahan siklus dari waktu ke waktu.</p>

<h4>2. Informasi yang Perlu Dicatat</h4>
<ul>
  <li>Tanggal mulai keluarnya darah.</li>
  <li>Tanggal berhentinya darah.</li>
  <li>Durasi perdarahan.</li>
  <li>Pola darah yang keluar, termasuk jika terputus-putus.</li>
  <li>Tanda berakhirnya haid.</li>
  <li>Keluhan fisik yang menyertai menstruasi.</li>
</ul>

<h4>3. Manfaat Pencatatan</h4>
<ul>
  <li>Mengenali kebiasaan dan pola siklus menstruasi.</li>
  <li>Membantu memperkirakan waktu menstruasi berikutnya.</li>
  <li>Memudahkan pencatatan masa suci.</li>
  <li>Membantu melihat perubahan durasi dan pola perdarahan.</li>
  <li>Memudahkan peninjauan fikih ketika terjadi perdarahan yang tidak biasa.</li>
</ul>

<h4>4. Contoh Pencatatan</h4>
<div class=\"alert alert-light border mt-3\">
  <strong>Contoh riwayat menstruasi</strong>
  <p class=\"mb-0 mt-2\">Mulai menstruasi: 1 November. Berakhir: 6 November. Durasi: 6 hari. Status: selesai. Catatan: tidak ada keluhan khusus.</p>
</div>

<h4>5. Hubungan Pencatatan dengan Fikih</h4>
<p>Dalam pembahasan Mazhab Syafi’i, kebiasaan haid atau adat memiliki peran penting ketika menentukan hukum darah pada kondisi tertentu. Oleh karena itu, pencatatan riwayat menstruasi dapat membantu perempuan mengingat kebiasaan yang pernah dialami.</p>

<h4>6. Penggunaan Prediksi</h4>
<p>Prediksi menstruasi dibuat berdasarkan pola siklus sebelumnya. Hasil prediksi hanya berupa perkiraan dan tidak menjamin tanggal menstruasi berikutnya akan sama.</p>

<div class=\"alert alert-light border mt-4\">
  <strong>Catatan Penting</strong>
  <p class=\"mb-0 mt-2\">Catatan menstruasi dan prediksi kalender NISAYA merupakan alat bantu informasi. Sistem tidak dapat menggantikan pemeriksaan medis atau menetapkan hukum fikih secara otomatis.</p>
</div>",
      'references' => "Pedoman kesehatan menstruasi.\nKitab Al-Majmu’ karya Imam An-Nawawi.\nMateri fikih perempuan Mazhab Syafi’i."
    ]
  ];

  $stmt = $pdo->prepare("INSERT INTO education_articles (id, title, slug, category, icon, description, content, references_text, status, author_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', 1, NOW())");
  foreach ($articles as $art) {
    $stmt->execute([
      $art['id'],
      $art['title'],
      $art['slug'],
      $art['category'],
      $art['icon'],
      $art['description'],
      $art['content'],
      $art['references']
    ]);
    echo "Inserted article {$art['id']}: {$art['title']}\n";
  }
}

echo "Migration finished successfully.\n";
