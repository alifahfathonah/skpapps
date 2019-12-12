<?php
defined('BASEPATH') or exit('No direct script access allowed');

require FCPATH .  "/vendor/autoload.php";

use Araditama\AuthSIAM\AuthSIAM;

class Auth extends CI_Controller
{
    private $username;
    private $password;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
    }

    public function index()
    {
        if ($this->session->userdata('user_profil_kode')) {
            redirect(link_dashboard($this->session->userdata('user_profil_kode')));
        }

        $this->form_validation->set_rules('username', 'Username', 'required|trim');
        $this->form_validation->set_rules('password', 'Password', 'required|trim');

        if ($this->form_validation->run() == false) {
            $data['title'] = 'SKP-APPS Login';
            $this->load->view('auth/login', $data);
        } else {
            // validasi success
            $this->_login();
        }
    }
    private function _login()
    {
        $auth = new AuthSIAM;

        $this->username = $this->input->post('username');
        $this->password = $this->input->post('password');
        // contoh array dari credentials yang akan diproses
        $data = [
            'nim' => $this->username,
            'password' => $this->password
        ];
        // memanggil method auth dari objek yang telah dibuat dengan method GET
        $result = $auth->auth($data);
        if ($result['msg'] == "true") {
            $data = [
                "username" => $result['data']['nim'],
                "nama" => $result['data']['nama'],
                "user_profil_kode" => $result['data']['status']
            ];
            $this->session->set_userdata($data);
            redirect('Mahasiswa');
        } else {

            $user = $this->db->get_where('user', ['username' => $this->username])->row_array();
            if ($user != null) {
                if ($user['is_active'] == 1) {
                    // cek password
                    if (password_verify($this->password, $user['password'])) {
                        $data = [
                            'username' => $user['username'],
                            "nama" => $user['nama'],
                            'user_profil_kode' => $user['user_profil_kode']
                        ];

                        $this->session->set_userdata($data);
                        if ($user['user_profil_kode'] == 2 || $user['user_profil_kode'] == 3) {
                            redirect('Kegiatan');
                        } elseif ($user['user_profil_kode'] == 4) {
                            redirect('Kemahasiswaan');
                        } elseif ($user['user_profil_kode'] == 5) {
                            redirect('Pimpinan');
                        } elseif ($user['user_profil_kode'] == 6) {
                            redirect('Keuangan');
                        } elseif ($user['user_profil_kode'] == 7) {
                            redirect('Publikasi');
                        } elseif ($user['user_profil_kode'] == 8) {
                            redirect('Akademik');
                        } elseif ($user['user_profil_kode'] == 9) {
                            redirect('Admin');
                        }
                    } else {
                        $this->session->set_flashdata('message', '<div class="px-5 alert alert-danger text-center" role="alert">Wrong Password !</div> ');
                        redirect('Auth');
                    }
                }
            } else {
                $this->session->set_flashdata('message', '<div class="px-5 alert alert-danger text-center" role="alert">Data tidak ditemukan !</div> ');
                redirect('Auth');
            }
        }
    }
    public function logout()
    {
        $this->session->unset_userdata('username');
        $this->session->unset_userdata('nama');
        $this->session->unset_userdata('user_profil_kode');

        $this->session->set_flashdata('message', '<div class="alert alert-success text-center align-middle mb-3" role="alert"><p>Logout berhasil</p></div>');
        redirect('auth');
    }
    public function blocked()
    {
        $this->load->view('error403');
    }
}
