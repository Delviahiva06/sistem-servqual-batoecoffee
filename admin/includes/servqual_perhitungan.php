<?php
function formatServqualAngka($nilai) {
    if ($nilai === null) return '&mdash;';
    return number_format((float)$nilai, 2, '.', '');
}
function tentukanKeteranganGap($gap) {
    if ($gap === null) return '&mdash;';
    if ($gap < 0) return 'Belum Memenuhi Harapan';
    if ($gap > 0) return 'Melebihi Harapan';
    return 'Sesuai Harapan';
}
function hitungRataRataServqual(array $nilai) {
    return count($nilai) ? array_sum($nilai) / count($nilai) : null;
}
function ambilHasilServqualPerPertanyaan($conn, $periode = 'semua') {
    $hasil = array();
    $join = "LEFT JOIN tbl_jawaban j ON j.id_pertanyaan = p.id_pertanyaan";
    if ($periode !== 'semua' && preg_match('/^\d{4}-\d{2}$/', $periode)) {
        list($tahun,$bulan)=array_map('intval',explode('-',$periode));
        $join = "LEFT JOIN tbl_jawaban j ON j.id_pertanyaan=p.id_pertanyaan
                 AND j.id_responden IN (
                    SELECT r.id_responden FROM tbl_responden r
                    WHERE YEAR(r.tanggal)=$tahun AND MONTH(r.tanggal)=$bulan
                 )";
    }
    $sql = "SELECT p.id_pertanyaan,p.kode,p.pertanyaan,d.id_dimensi,d.nama_dimensi,
                   AVG(j.skor_harapan) rata_harapan,AVG(j.skor_kenyataan) rata_kenyataan,
                   COUNT(j.id_jawaban) jumlah_jawaban
            FROM tbl_pertanyaan p
            INNER JOIN tbl_dimensi d ON d.id_dimensi=p.id_dimensi
            $join
            GROUP BY p.id_pertanyaan,p.kode,p.pertanyaan,d.id_dimensi,d.nama_dimensi
            ORDER BY p.id_pertanyaan ASC";
    $q=mysqli_query($conn,$sql);
    if (!$q) return $hasil;
    while($b=mysqli_fetch_assoc($q)){
        $n=(int)$b['jumlah_jawaban'];
        $h=$n>0?(float)$b['rata_harapan']:null;
        $k=$n>0?(float)$b['rata_kenyataan']:null;
        $g=($h!==null&&$k!==null)?$k-$h:null;
        $hasil[]=array(
            'id_pertanyaan'=>(int)$b['id_pertanyaan'],'kode'=>$b['kode'],
            'pertanyaan'=>$b['pertanyaan'],'id_dimensi'=>(int)$b['id_dimensi'],
            'dimensi'=>$b['nama_dimensi'],'rata_harapan'=>$h,
            'rata_kenyataan'=>$k,'gap'=>$g,'jumlah_jawaban'=>$n
        );
    }
    return $hasil;
}
function hitungRekapServqualPerDimensi(array $rows) {
    $a=array();
    foreach($rows as $b){
        if($b['rata_harapan']===null||$b['rata_kenyataan']===null) continue;
        $id=$b['id_dimensi'];
        if(!isset($a[$id])) $a[$id]=array('id_dimensi'=>$id,'dimensi'=>$b['dimensi'],'h'=>array(),'k'=>array());
        $a[$id]['h'][]=$b['rata_harapan'];
        $a[$id]['k'][]=$b['rata_kenyataan'];
    }
    $r=array();
    foreach($a as $id=>$d){
        $h=hitungRataRataServqual($d['h']); $k=hitungRataRataServqual($d['k']); $g=$k-$h;
        $r[]=array('id_dimensi'=>$id,'dimensi'=>$d['dimensi'],'rata_harapan'=>$h,
                   'rata_kenyataan'=>$k,'gap'=>$g,'keterangan'=>tentukanKeteranganGap($g));
    }
    usort($r,function($x,$y){return $x['id_dimensi']-$y['id_dimensi'];});
    return $r;
}
function hitungServqual($conn,$periode='semua') {
    $p=ambilHasilServqualPerPertanyaan($conn,$periode);
    return array('pertanyaan'=>$p,'dimensi'=>hitungRekapServqualPerDimensi($p));
}
function servqualMemilikiDataJawaban(array $rows) {
    foreach($rows as $b) if($b['jumlah_jawaban']>0) return true;
    return false;
}
