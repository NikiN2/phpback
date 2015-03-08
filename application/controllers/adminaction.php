<?php
/*********************************************************************
PHPBack
Ivan Diaz <ivan@phpback.org>
Copyright (c) 2014 PHPBack
http://www.phpback.org
Released under the GNU General Public License WITHOUT ANY WARRANTY.
See LICENSE.TXT for details.
**********************************************************************/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Adminaction extends CI_Controller{
    
	public function __construct(){
		parent::__construct();
		$this->load->helper('url');
		$this->load->model('get');
		$this->load->model('post');

        $this->lang->load('log', 'english');
	}

	public function login(){
		session_start();
		$email = $this->input->post('email', true);
		$pass = $this->input->post('password', true);
		$result = $this->get->login($email, $pass);
		
		if($result != 0){
			$user = $this->get->get_user_info($result);
			if(!$user->isadmin){
				header('Location: ' . base_url() . 'admin/index/error');
				exit;
			}
			$_SESSION['phpback_userid'] = $user->id;
			$_SESSION['phpback_username'] = $user->name;
			$_SESSION['phpback_useremail'] = $user->email;
			$_SESSION['phpback_isadmin'] = $user->isadmin;
			header('Location: ' . base_url() . 'admin/dashboard');
		}
		else{
			header('Location: ' . base_url() . 'admin/index/error'); 
		}
	}

	public function banuser(){
		$this->start(2);

		$id = $this->input->post('id', true);
		$days = $this->input->post('days', true);
		
		if($days == 0) $days = -1;
		else{
			date_default_timezone_set('America/Los_Angeles');
			$days = date('Ymd', strtotime("+$days days"));
		}
		$sql = $this->post->do_ban($id, $days);
		$this->post->log(str_replace(['%s1', '%s2'], ["#$id", $days], $this->lang->language['log_user_banned']), 'user', $_SESSION['phpback_userid']);
		$this->post->log(str_replace('%s', '#$id', $this->lang->language['log_user_was_banned']), 'user', $id);
		header('Location: ' . base_url() . 'admin/users');
	}

	public function unban($userid){
		$this->start(2);
		$this->post->unban($userid);
		$this->post->log(str_replace('%s', "#$userid", $this->lang->language['log_user_unbanned']), 'user', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . 'admin/users');
	}

	public function deletecomment($commentid){
		$this->start(1);
		$this->post->deletecomment($commentid);
		$this->post->log(str_replace('%s', "#$commentid", $this->lang->language['log_comment_deleted']), 'user', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . "admin/ideas");
	}

	public function deleteidea($id){
		$this->start(1);
		$this->post->deleteidea($id);
		$this->post->log(str_replace('%s', "#$id", $this->lang->language['log_idea_deleted']), 'user', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . "home/");
	}

	public function approveidea($id){
		$this->start(1);
		$this->post->approveidea($id);
		$this->post->log(str_replace('%s', "#$id", $this->lang->language['log_idea_approved']), 'user', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . "home/idea/$id");
	}

	public function ideastatus($status, $id){
		$this->start(1);
		$this->post->change_status($id, $status);
		$this->post->log(str_replace(['%s1', '%s2'], ["#$id", $status], $this->lang->language['log_idea_status']), 'user', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . "home/idea/$id");
	}

	public function editsettings(){
		$this->start(3);
		$settings = $this->get->get_all_settings();
		foreach($settings as $setting){
			$value = $this->input->post('setting-' . $setting->id, true);
			$this->post->update_by_id('settings', 'value', $value, $setting->id);
		}
		$this->post->log($this->lang->language['log_settings'], 'system', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . 'admin/system');
	}

	public function editadmin(){
		$this->start(3);
		$id = $this->input->post('id', true);
		$level = $this->input->post('level', true);
		if($_SESSION['phpback_userid'] != $id){
			if($this->post->updateadmin($id, $level))
				$this->post->log($this->lang->language['log_user_admin'], 'user', $id);
		}
		header('Location: ' . base_url() . 'admin/system');
	}

	public function addcategory(){
		$this->start(3);
		$name = $this->input->post('name', true);
		$description = $this->input->post('description', true);
		$result = $this->get->category_id($name);
		if ($result){
			$this->post->update_by_id('categories', 'description', $description, $result);
			$this->post->log("'$name'" . $this->lang->language['log_category_description'], 'user', $_SESSION['phpback_userid']);
		}
		else{
			$this->post->add_category($name, $description);
			$this->post->log("'$name'" . $this->lang->language['log_category_created'], 'user', $_SESSION['phpback_userid']);
		}

		header('Location: ' . base_url() . 'admin/system');
	}

	public function updatecategories(){
		$this->start(3);
		$categories = $this->get->get_categories();
		foreach ($categories as $cat) {
			$temp = $this->input->post("$cat->id", true);
			if($temp != $cat->name){
				$this->post->update_by_id('categories', 'name', $temp , $cat->id);
				$this->post->log(str_replace(['%s1', '%s2'], [$cat->name, $temp], $this->lang->language['log_category_changed']), 'user', $_SESSION['phpback_userid']);
			}
		}
		header('Location: ' . base_url() . 'admin/system');
	}

	public function deletecategory(){
		$this->start(3);
		$id = $this->input->post('catid', true);
		if($this->input->post('ideas', true)){
			$ideas = $this->get->get_ideas_by_category($id , 'id', 'desc', 0);
			foreach ($ideas as $idea){
				$this->post->deleteidea($idea->id);
			}
		}
		$this->post->delete_category($id);
		$this->post->log(str_replace('%s', "#$catid", $this->lang->language['log_category_deleted']), 'user', $_SESSION['phpback_userid']);
		header('Location: ' . base_url() . 'admin/system');
	}

	private function start($level = 1){
        session_start();
        if(!isset($_SESSION['phpback_isadmin']) || $_SESSION['phpback_isadmin'] < $level){
            header('Location: ' . base_url() . 'admin/');
            exit;
        }
    }
}