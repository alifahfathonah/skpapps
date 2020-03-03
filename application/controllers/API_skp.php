<?php
defined('BASEPATH') or exit('No direct script access allowed');
class API_skp extends CI_Controller
{
    private $kondisi;
    private $tahun;
    private $id_lembaga;
    private $nim;
    private $id_kegiatan;
    public function __construct()
    {
        parent::__construct();
    }

    public function gabungKegiatan($id_kegiatan = null)
    {
        if ($id_kegiatan != null) {
            $this->session->set_userdata('id_kegiatan', intval($id_kegiatan));
        } else {
            $id_kegiatan = $this->session->userdata('id_kegiatan');
        }

        if (!$this->session->userdata('username')) {
            redirect('auth');
        } else {
            $data_peserta_kuliah_tamu = [
                'nim' => $this->session->userdata('username'),
                'id_kuliah_tamu' => intval($id_kegiatan),
                'kehadiran' => 1
            ];

            $data_kuliah_tamu = $this->db->get_where('kuliah_tamu', ['id_kuliah_tamu' => intval($id_kegiatan)])->row_array();
            // var_dump($data_kuliah_tamu);
            // die;

            // Jarak dua hari, kuliah tamu sama sekarang
            $awal  = date_create($data_kuliah_tamu['tanggal_event']);
            $akhir = date_create(); // waktu sekarang
            $diff  = date_diff($awal, $akhir);
            $beda_hari = $diff->format("%R%a");

            // var_dump($beda_hari);die;

            $tanda_beda = substr($beda_hari, 0, 1);
            $hari_ini = substr($beda_hari, 0, 2);

            if ($tanda_beda == "+" && $hari_ini != "+0") {
                // Tidak Bisa join, Tanggal Kelebihan
                $this->session->unset_userdata('id_kegiatan');
                $this->session->set_flashdata('failed', 'Pendaftaran kuliah tamu tidak berhasil, kuliah tamu sudah terlewat');
                redirect(base_url('Mahasiswa'));
            } else {
                // Tambahan Scan Sesuai kondisi
                if ($data_kuliah_tamu['status_terlaksana'] == 1) {
                    $this->session->unset_userdata('id_kegiatan');
                    $this->session->set_flashdata('failed', 'Pendaftaran kuliah tamu tidak berhasil, kuliah tamu sudah di validasi');
                    redirect(base_url('Mahasiswa'));
                } else {
                    // Cek duplikasi pendaftar
                    $data = $this->db->get_where('peserta_kuliah_tamu', ['nim' => $data_peserta_kuliah_tamu['nim'], 'id_kuliah_tamu' => $data_peserta_kuliah_tamu['id_kuliah_tamu']])->result_array();
                    
                    // Belum ada pendaftar
                    if($data == null){

                        // Insert ke tabel poin skp (keep / proses)
                        $data_poin_skp = [
                            'nim' => $this->session->userdata('username'),
                            'nama_kegiatan' => $data_kuliah_tamu['nama_event'],
                            'validasi_prestasi' => 3,
                            'tgl_pengajuan' => date('Y-m-d'),
                            'tgl_pelaksanaan' => $data_kuliah_tamu['tanggal_event'],
                            'tempat_pelaksanaan' => $data_kuliah_tamu['lokasi'],
                            'prestasiid_prestasi' => 115
                        ];
                        // header('Content-type: application/json');
                        // echo json_encode($data_poin_skp);
                        // die;
                        $this->db->insert('poin_skp', $data_poin_skp);

                        // Insert ke tabel peserta kuliah tamu
                        $this->db->insert('peserta_kuliah_tamu', $data_peserta_kuliah_tamu);
                        
                        $this->session->unset_userdata('id_kegiatan');
                        $this->session->set_flashdata('message', 'Pendaftaran kuliah tamu berhasil');
                        redirect(base_url('Mahasiswa'));
                    }
                    // Pendaftar Duplikat
                    else{
                        $this->session->unset_userdata('id_kegiatan');
                        $this->session->set_flashdata('failed', 'Pendaftaran kuliah tamu tidak berhasil, Anda sudah terdaftar pada kuliah tamu tersebut');
                        redirect(base_url('Mahasiswa'));
                    }

                    
                    
                }
            }
        }
    }
    //  mmenampilkan data validasi
    public function validasiKegiatan($id)
    {
        $this->load->model('Model_kegiatan', 'kegiatan');
        $data['validasi'] = $this->kegiatan->getInfoValidasi($id);
        echo json_encode($data['validasi']);
    }
    // menampilkan daftar mahasiswa keseluruhan
    public function daftarMahasiswa()
    {
        $this->load->model('Model_mahasiswa', 'mahasiswa');
        $data['mahasiswa'] = $this->mahasiswa->getDataMahasiswa();
        echo json_encode($data['mahasiswa']);
    }
    // Filter Kategori Detail Tingkatan
    public function getDataDetail()
    {
        $data['prestasi'] = $this->db->get('prestasi')->result_array();
        $data['tingkatan'] = $this->db->get('tingkatan')->result_array();
        $data['jenis_kegiatan'] = $this->db->get('jenis_kegiatan')->result_array();
        $data['bidang_kegiatan'] = $this->db->get('bidang_kegiatan')->result_array();
        header('Content-type: application/json');
        echo json_encode($data);
    }
    // menampilkan bidang kegiatan
    public function bidangKegiatan()
    {
        $this->bidangKegiatan = $this->db->get('bidang_kegiatan')->result_array();
        echo json_encode($this->bidangKegiatan);
    }

    // menampilkan bidang kegiatan secara spesifik
    public function getBidangKegiatan($id)
    {
        $this->bidangKegiatan = $this->db->get_where('bidang_kegiatan', ['id_bidang' => $id])->row_array();
        echo json_encode($this->bidangKegiatan);
    }

    // menampilkan jenis kegiatan
    public function jenisKegiatan($id_bidang)
    {
        $this->jenisKegiatan = $this->db->get_where('jenis_kegiatan', ['id_bidang' => $id_bidang])->result_array();
        echo json_encode($this->jenisKegiatan);
    }
    // menampilkan jenis kegiatan secara spesifik
    public function getJenisKegiatan($id)
    {
        $this->db->where('id_jenis_kegiatan', $id);
        // $this->db->select('id_jenis_kegiatan, jenis_kegiatan, nama_bidang');
        $this->db->from('jenis_kegiatan');
        $this->db->join('bidang_kegiatan', 'jenis_kegiatan.id_bidang = bidang_kegiatan.id_bidang');
        $data['jenisKegiatan'] = $this->db->get()->row_array();
        $data['semua_bidang'] = $this->db->get('bidang_kegiatan')->result_array();
        header('Content-type: application/json');
        echo json_encode($data);
    }
    // menampilkan  tingkat kegiatan keseluruahn
    public function tingkatKegiatan($id_jenis)
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $this->tingkatKegiatan = $this->poinskp->getTingkatSkp($id_jenis);
        echo json_encode($this->tingkatKegiatan);
    }

    public function getTingkatan($id)
    {
        $data = $this->db->get_where('tingkatan', ['id_tingkatan' => $id])->row_array();
        echo json_encode($data);
    }
    public function getDetailTingkatan($id)
    {
        $this->db->select('id_semua_tingkatan, id_tingkatan, jenis_kegiatan.id_jenis_kegiatan, bidang_kegiatan.id_bidang');
        $this->db->where('semua_tingkatan.id_semua_tingkatan', $id);
        $this->db->from('semua_tingkatan');
        $this->db->join('jenis_kegiatan', 'semua_tingkatan.id_jenis_kegiatan = jenis_kegiatan.id_jenis_kegiatan');
        $this->db->join('bidang_kegiatan', 'jenis_kegiatan.id_bidang = bidang_kegiatan.id_bidang');
        $data['real'] = $this->db->get()->row_array();
        $data['list_bidang'] = $this->db->get('bidang_kegiatan')->result_array();
        $data['list_jenis'] = $this->db->get_where('jenis_kegiatan', ['id_bidang' => $data['real']['id_bidang']])->result_array();
        $data['list_tingkatan'] = $this->db->get('tingkatan')->result_array();

        echo json_encode($data);
    }
    public function getDetailPrestasi($id)
    {

        $this->db->where('id_semua_prestasi', intval($id));
        $this->db->from('semua_prestasi');
        $this->db->join('prestasi', 'semua_prestasi.id_prestasi = prestasi.id_prestasi');
        $this->db->join('dasar_penilaian', 'semua_prestasi.id_dasar_penilaian = dasar_penilaian.id_dasar_penilaian');
        $this->db->join('semua_tingkatan', 'semua_prestasi.id_semua_tingkatan = semua_tingkatan.id_semua_tingkatan');
        $this->db->join('tingkatan', 'semua_tingkatan.id_tingkatan = tingkatan.id_tingkatan');
        $this->db->join('jenis_kegiatan', 'semua_tingkatan.id_jenis_kegiatan = jenis_kegiatan.id_jenis_kegiatan');
        $this->db->join('bidang_kegiatan', 'jenis_kegiatan.id_bidang = bidang_kegiatan.id_bidang');

        $data['real'] = $this->db->get()->row_array();
        $data['list_bidang'] = $this->db->get('bidang_kegiatan')->result_array();
        $data['list_jenis'] = $this->db->get_where('jenis_kegiatan', ['id_bidang' => $data['real']['id_bidang']])->result_array();
        $this->load->model('Model_poinskp', 'poinskp');
        $this->semuaTingkatanKegiatan = $this->poinskp->getSemuaTingkatanJenis($data['real']['id_jenis_kegiatan']);
        $data['list_tingkatan'] = $this->semuaTingkatanKegiatan;
        $data['list_prestasi'] = $this->db->get('prestasi')->result_array();
        $data['list_dasar'] = $this->db->get('dasar_penilaian')->result_array();
        header('Content-type: application/json');
        echo json_encode($data);
    }
    public function getTingkat()
    {
        $data = $this->db->get('tingkatan')->result_array();
        echo json_encode($data);
    }
    public function getPres()
    {
        $data['prestasi'] = $this->db->get('prestasi')->result_array();
        $data['dasar_penilaian'] = $this->db->get('dasar_penilaian')->result_array();
        echo json_encode($data);
    }
    public function getSemuaTingkatanKegiatan()
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $this->semuaTingkatanKegiatan = $this->poinskp->getSemuaTingkatan();
        header('Content-type: application/json');
        echo json_encode($this->semuaTingkatanKegiatan);
    }
    public function getSemuaTingkatanJenis($id)
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $this->semuaTingkatanKegiatan = $this->poinskp->getSemuaTingkatanJenis($id);
        header('Content-type: application/json');
        echo json_encode($this->semuaTingkatanKegiatan);
    }
    public function getSemuaPrestasiKegiatan()
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $this->semuaPrestasiKegiatan = $this->poinskp->getSemuaPrestasi();
        header('Content-type: application/json');
        echo json_encode($this->semuaPrestasiKegiatan);
    }
    public function getPrestasi($id)
    {
        $data = $this->db->get_where('prestasi', ['id_prestasi' => $id])->row_array();
        echo json_encode($data);
    }
    public function getDasarPenilaian($id)
    {
        $data = $this->db->get_where('dasar_penilaian', ['id_dasar_penilaian' => $id])->row_array();
        echo json_encode($data);
    }

    // menampilkan partisipasi kegiatan berdasarkan semua tingkat
    public function partisipasiKegiatan($id_sm_tingkat)
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $this->partisipasiKegiatan = $this->poinskp->getPrestasi($id_sm_tingkat);
        echo json_encode($this->partisipasiKegiatan);
    }

    // menampilkan bobot kegiatan
    public function bobotKegiatan($id_sm_prestasi)
    {
        $this->bobotKegiatan = $this->db->get_where('semua_prestasi', ['id_semua_prestasi' => $id_sm_prestasi])->result_array();
        echo json_encode($this->bobotKegiatan);
    }
    // menampilkan detail kegiatan
    public function detailKegiatan($id_kegiatan = null)
    {
        $this->load->model('Model_poinskp', 'poinskp');
        echo json_encode($this->poinskp->getPoinSkp($this->session->userdata('username'), $id_kegiatan));
    }

    // menampilkan tingkat anggota
    public function getTingkatAnggota($id_kegiatan)
    {
        $this->load->model('Model_kegiatan', 'kegiatan');
        $data = $this->kegiatan->getInfoTingkat($id_kegiatan);
        echo json_encode($data);
    }
    // menampilkan info anggota
    public function infoKegiatan($id_kegiatan)
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $this->load->model('Model_kegiatan', 'kegiatan');
        $data['kegiatan'] = $this->kegiatan->getInfoKegiatan($id_kegiatan);
        $data['dana'] = $this->kegiatan->getInfoDana($id_kegiatan);
        $data['anggota'] = $this->kegiatan->getInfoAnggota($id_kegiatan);
        $data['tingkat'] = $this->kegiatan->getInfoTingkat($id_kegiatan);
        $data['dokumentasi'] = $this->kegiatan->getDokumentasi($id_kegiatan);
        echo json_encode($data);
    }
    // menampilkan data lembaga
    public function dataLembaga()
    {
        $this->load->model('Model_kemahasiswaan', 'kemahasiswaan');
        $data['lembaga'] = $this->kemahasiswaan->getInfoLembaga('lembaga');
        echo json_encode($data['lembaga']);
    }

    // menampilkan data anggaran
    public function dataAnggaran($id_lembaga)
    {
        $this->load->model('Model_kemahasiswaan', 'kemahasiswaan');
        $tahun = $this->input->get('tahun');
        $this->db->select('rkl.*,l.nama_lembaga');
        $this->db->from('rekapan_kegiatan_lembaga as rkl');
        $this->db->join('lembaga as l', 'l.id_lembaga = rkl.id_lembaga', 'left');
        $this->db->where('rkl.tahun_pengajuan', $tahun);
        $this->db->where('l.id_lembaga', $id_lembaga);
        $data =  $this->db->get()->row_array();
        echo json_encode($data);
    }

    // menampilkan jumlah kegiatan
    public function dataJumlahKegiatan($id_lembaga)
    {
        $this->load->model("Model_kemahasiswaan", 'kemahasiswaan');
        $this->kondisi = $this->input->get('kondisi');
        $this->tahun = $this->input->get('tahun');
        $this->id_lembaga = $id_lembaga;
        if ($this->kondisi == 'terlaksana_blm_lpj') {
            $data = $this->kemahasiswaan->getDetailAnggaranBlmLpj($this->id_lembaga, $this->tahun);
        } else {

            $data = $this->kemahasiswaan->getDetailAnggaranLembaga($this->id_lembaga, $this->tahun, $this->kondisi);
        }
        echo json_encode($data);
    }



    // menampilkan laporan serapan kegiatan
    public function laporanSerapan($tahun)
    {
        $this->load->model('Model_keuangan', 'keuangan');
        $data['serapan_proposal'] = $this->keuangan->getLaporanSerapanProposal($tahun);
        $data['serapan_lpj'] = $this->keuangan->getLaporanSerapanLpj($tahun);
        $data['lembaga'] = $this->db->get('lembaga')->result_array();
        $data['tahun'] = $this->keuangan->getTahun();
        $tahun = $data['tahun'][0]['tahun'];
        if ($tahun) {
            $data['laporan'] = $this->_serapan($data['serapan_proposal'], $data['serapan_lpj'], $tahun);
        } else {
            $data['laporan'] = $this->_serapan($data['serapan_proposal'], $data['serapan_lpj'], $tahun);
        }
        $data['total'] = $this->_totalDana($data['laporan']);
        echo json_encode($data['laporan']);
    }

    // mengolah data serapan anggaran
    private function _serapan($proposal, $lpj, $tahun)
    {
        $lembaga = $this->db->get_where('lembaga', ['id_lembaga !=' => 0])->result_array();
        if ($proposal == null) {
            foreach ($lembaga as $l) {
                $proposal[$l['id_lembaga']] = [
                    'bulan' => 0,
                    'dana' => 0,
                    'id_lembaga' => $l['id_lembaga'],
                    'nama_lembaga' => $l['nama_lembaga']
                ];
            }
        }
        if ($lpj == null) {
            foreach ($lembaga as $l) {
                $lpj[$l['id_lembaga']] = [
                    'bulan' => 0,
                    'dana' => 0,
                    'id_lembaga' => $l['id_lembaga'],
                    'nama_lembaga' => $l['nama_lembaga']
                ];
            }
        }
        // inisialisasi data lpj
        $data_lpj = [];
        $index1 = 0;
        foreach ($proposal as $p) {
            $data_lpj[$index1++] = [
                'bulan' => 0,
                'dana' => 0,
                'id_lembaga' => $p['id_lembaga'],
                'nama_lembaga' => $p['nama_lembaga']
            ];
        }
        // mengisikan nilai array LPJ
        $index2 = 0;
        foreach ($lpj as $l) {
            $data_lpj[$index2++] = [
                'bulan' => $l['bulan'],
                'dana' => $l['dana'],
                'id_lembaga' => $l['id_lembaga'],
                'nama_lembaga' => $l['nama_lembaga']
            ];
        }
        $lpj = $data_lpj;
        $data = [];
        foreach ($lembaga as $l) {
            for ($j = 1; $j < 13; $j++) {
                $data[$l['id_lembaga']][$j] = 0;
            }
            $data[$l['id_lembaga']]['nama_lembaga'] = $l['nama_lembaga'];
            $dana = $this->db->select('anggaran_kemahasiswaan')->get_where('rekapan_kegiatan_lembaga', ['id_lembaga' => $l['id_lembaga'],  'tahun_pengajuan' => $tahun])->row_array();

            if ($dana['anggaran_kemahasiswaan'] == null) {
                $data[$l['id_lembaga']]['dana_pagu'] = 0;
            } else {
                $data[$l['id_lembaga']]['dana_pagu']  = $dana['anggaran_kemahasiswaan'];
            }
            $data[$l['id_lembaga']]['dana_terserap'] = 0;
        }
        foreach ($proposal as $p) {
            if ($p['bulan'] == "1") {
                $data[$p['id_lembaga']][1] += $p['dana'];
            } elseif ($p['bulan'] == "2") {
                $data[$p['id_lembaga']][2] += $p['dana'];
            } elseif ($p['bulan'] == "3") {
                $data[$p['id_lembaga']][3] += $p['dana'];
            } elseif ($p['bulan'] == "4") {
                $data[$p['id_lembaga']][4] += $p['dana'];
            } elseif ($p['bulan'] == "5") {
                $data[$p['id_lembaga']][5] += $p['dana'];
            } elseif ($p['bulan'] == "6") {
                $data[$p['id_lembaga']][6] += $p['dana'];
            } elseif ($p['bulan'] == "7") {
                $data[$p['id_lembaga']][7] += $p['dana'];
            } elseif ($p['bulan'] == "8") {
                $data[$p['id_lembaga']][8] += $p['dana'];
            } elseif ($p['bulan'] == "9") {
                $data[$p['id_lembaga']][9] += $p['dana'];
            } elseif ($p['bulan'] == "10") {
                $data[$p['id_lembaga']][10] += $p['dana'];
            } elseif ($p['bulan'] == "11") {
                $data[$p['id_lembaga']][11] += $p['dana'];
            } elseif ($p['bulan'] == "12") {
                $data[$p['id_lembaga']][12] += $p['dana'];
            }
        }
        foreach ($lpj as $l) {
            if ($l['bulan'] == "1") {
                $data[$l['id_lembaga']][1] += $l['dana'];
            } elseif ($l['bulan'] == "2") {
                $data[$l['id_lembaga']][2] += $l['dana'];
            } elseif ($l['bulan'] == "3") {
                $data[$l['id_lembaga']][3] += $l['dana'];
            } elseif ($l['bulan'] == "4") {
                $data[$l['id_lembaga']][4] += $l['dana'];
            } elseif ($l['bulan'] == "5") {
                $data[$l['id_lembaga']][5] += $l['dana'];
            } elseif ($l['bulan'] == "6") {
                $data[$l['id_lembaga']][6] += $l['dana'];
            } elseif ($l['bulan'] == "7") {
                $data[$l['id_lembaga']][7] += $l['dana'];
            } elseif ($l['bulan'] == "8") {
                $data[$l['id_lembaga']][8] += $l['dana'];
            } elseif ($l['bulan'] == "9") {
                $data[$l['id_lembaga']][9] += $l['dana'];
            } elseif ($l['bulan'] == "10") {
                $data[$l['id_lembaga']][10] += $l['dana'];
            } elseif ($l['bulan'] == "11") {
                $data[$l['id_lembaga']][11] += $l['dana'];
            } elseif ($l['bulan'] == "12") {
                $data[$l['id_lembaga']][12] += $l['dana'];
            }
        }
        foreach ($lembaga as $l) {
            for ($j = 1; $j < 13; $j++) {
                $data[$l['id_lembaga']]['dana_terserap'] += $data[$l['id_lembaga']][$j];
            }
            if ($data[$l['id_lembaga']]['dana_pagu'] == 0) {
                $data[$l['id_lembaga']]['terserap_persen'] =  0;
            } else {
                $data[$l['id_lembaga']]['terserap_persen'] = $data[$l['id_lembaga']]['dana_terserap'] / $data[$l['id_lembaga']]['dana_pagu']  * 100;
            }
            $data[$l['id_lembaga']]['dana_sisa'] = $data[$l['id_lembaga']]['dana_pagu'] - $data[$l['id_lembaga']]['dana_terserap'];
            if ($data[$l['id_lembaga']]['dana_pagu'] == 0) {
                $data[$l['id_lembaga']]['sisa_terserap'] = 0;
            } else {
                $data[$l['id_lembaga']]['sisa_terserap'] = $data[$l['id_lembaga']]['dana_sisa'] / $data[$l['id_lembaga']]['dana_pagu']  * 100;
            }
        }
        return $data;
    }

    // mengolah total dana serapan laporan
    private function _totalDana($laporan)
    {

        $lembaga = $this->db->get_where('lembaga', ['id_lembaga !=' => 0])->result_array();
        $data['total']['dana_sisa'] = 0;
        $data['total']['dana_terserap'] = 0;
        $data['total']['dana_pagu'] = 0;
        $data['total']['persen_terserap'] = 0;
        $data['total']['persen_sisa'] = 0;
        foreach ($lembaga as $l) {
            $data['total']['dana_sisa'] += $laporan[$l['id_lembaga']]['dana_sisa'];
            $data['total']['dana_terserap'] += $laporan[$l['id_lembaga']]['dana_terserap'];
            $data['total']['dana_pagu'] += $laporan[$l['id_lembaga']]['dana_pagu'];
        }
        if ($data['total']['dana_pagu'] == 0) {
            $data['total']['persen_terserap'] = 0;
            $data['total']['persen_sisa'] = 0;
        } else {
            $data['total']['persen_terserap'] = $data['total']['dana_terserap'] / $data['total']['dana_pagu'] * 100;
            $data['total']['persen_sisa'] = $data['total']['dana_sisa'] / $data['total']['dana_pagu'] * 100;
        }

        return $data;
    }

    public function penyebaranSkp()
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $data['poin_skp'] = $this->poinskp->getJumlahKategoriSkp();
        echo json_encode($data);
    }

    public function get_detail_mahasiswa($nim)
    {
        $this->db->where('mahasiswa.nim', $nim);
        $this->db->select('nim, nama, nama_prodi');
        $this->db->from('mahasiswa');
        $this->db->join('prodi', 'mahasiswa.kode_prodi = prodi.kode_prodi');
        $detail_mahasiswa = $this->db->get()->row_array();
        header('Content-type: application/json');
        echo json_encode($detail_mahasiswa);
    }
    public function get_detail_skp($nim)
    {
        $this->db->where('poin_skp.nim', $nim);
        // $this->db->select('*');
        $this->db->select('id_poin_skp, bobot, nama_prestasi, nama_tingkatan, jenis_kegiatan, nama_bidang, tgl_pelaksanaan, nama_dasar_penilaian');
        $this->db->from('poin_skp');
        $this->db->where('poin_skp.validasi_prestasi', 1);
        $this->db->join('semua_prestasi', 'poin_skp.prestasiid_prestasi = semua_prestasi.id_semua_prestasi');
        $this->db->join('prestasi', 'semua_prestasi.id_prestasi = prestasi.id_prestasi');
        $this->db->join('dasar_penilaian', 'semua_prestasi.id_dasar_penilaian = dasar_penilaian.id_dasar_penilaian');
        $this->db->join('semua_tingkatan', 'semua_prestasi.id_semua_tingkatan = semua_tingkatan.id_semua_tingkatan');
        $this->db->join('tingkatan', 'semua_tingkatan.id_tingkatan = tingkatan.id_tingkatan');
        $this->db->join('jenis_kegiatan', 'semua_tingkatan.id_jenis_kegiatan = jenis_kegiatan.id_jenis_kegiatan');
        $this->db->join('bidang_kegiatan', 'jenis_kegiatan.id_bidang = bidang_kegiatan.id_bidang');

        $detail_skp = $this->db->get()->result_array();
        header('Content-type: application/json');
        echo json_encode($detail_skp);
    }


    public function dataMahasiswa()
    {
        $this->load->model('Model_mahasiswa', 'mahasiswa');
        $data['mhs'] = $this->db->get('mahasiswa')->result_array();
        $data['mhs_kegiatan'] = [];
        $data['bkn_mhs_kegiatan'] = [];

        if ($this->input->get('id')) {
            $data['mhs_kegiatan'] = $this->mahasiswa->getAnggotaKegiatan($this->input->get('id'));
            $data['bkn_mhs_kegiatan'] = $this->mahasiswa->getBukanAnggotaKegiatan($this->input->get('id'));
        }
        echo json_encode($data);
    }

    public function beasiswa($id_beasiswa)
    {
        $data = $this->db->get_where('beasiswa', ['id' => $id_beasiswa])->row_array();
        echo json_encode($data);
    }

    public function kegiatanAkademik()
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $data['kegiatan_akademik'] = $this->poinskp->getKegiatanAkademik();
        echo json_encode($data);
    }

    public function pesertaKegiatanAkademik()
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $data['peserta_kegiatan_akademik'] = $this->poinskp->getPesertaKegiatanAkademik();
        echo json_encode($data);
    }

    public function rekapUser()
    {
        $this->load->model('Model_poinskp', 'poinskp');
        $data['rekap_user'] = $this->poinskp->getRekapUser();
        echo json_encode($data);
    }
    public function exportLaporanSerapan($tahun)
    {
        $this->load->model('Model_keuangan', 'keuangan');
        $data['title'] = 'Anggaran';
        $data['serapan_proposal'] = $this->keuangan->getLaporanSerapanProposal($tahun);
        $data['serapan_lpj'] = $this->keuangan->getLaporanSerapanLpj($tahun);
        $data['laporan'] = $this->_serapan($data['serapan_proposal'], $data['serapan_lpj'], $tahun);
        $data['tahun_saat_ini'] = $this->input->post('tahun');
        $data['total'] = $this->_totalDana($data['laporan']);
        $this->load->view("keuangan/export_keuangan", $data);
    }
    // Anggota Lembaga
    public function daftarAnggotaLembaga()
    {
        $id = intval($this->input->get('id'));
        $this->db->where('id_pengajuan_anggota_lembaga', $id);
        $anggota_lembaga = $this->db->get('daftar_anggota_lembaga')->result_array();
        echo json_encode($anggota_lembaga);
    }

    public function getDetailLembaga($id)
    {
        $this->db->where('id', intval($id));
        $this->db->from('pengajuan_anggota_lembaga');
        $this->db->join('lembaga', 'pengajuan_anggota_lembaga.id_lembaga = lembaga.id_lembaga');
        $lembaga = $this->db->get()->row_array();
        echo json_encode($lembaga);
    }

    public function getDetailAnggotaLembaga($id)
    {
        $this->db->where('id_pengajuan_anggota_lembaga', intval($id));
        $this->db->from('daftar_anggota_lembaga');
        $this->db->join('mahasiswa', 'daftar_anggota_lembaga.nim = mahasiswa.nim');
        $this->db->join('semua_prestasi', 'daftar_anggota_lembaga.id_sm_prestasi = semua_prestasi.id_semua_prestasi');
        $this->db->join('prestasi', 'semua_prestasi.id_prestasi = prestasi.id_prestasi');
        $anggota_lembaga = $this->db->get()->result_array();
        echo json_encode($anggota_lembaga);
    }

    public function getDataRuangan()
    {
        $json = file_get_contents('https://psik.feb.ub.ac.id/siruas-api/api/ruangan');
        echo $json;
    }
}
