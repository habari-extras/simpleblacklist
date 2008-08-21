<?php

class SimpleBlacklist extends Plugin
{
	const VERSION = '1.3-alpha';
	
	public function info()
	{
		return array(
			'name' => 'Simple Blacklist', 
			'url' => 'http://habariproject.org/', 
			'author' => 'Habari Community', 
			'authorurl' => 'http://habariproject.org/', 
			'version' => self::VERSION, 
			'description' => 'Anything defined here that exists in a comment (author name, URL, IP, body) will cause that comment to be silently discarded.', 
			'license' => 'Apache License 2.0'
		);
	}
	
	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[] = _t('Configure');
		}
		
		return $actions;
	}
	
	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
				case _t('Configure') :
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$blacklist= $ui->append( 'textarea', 'blacklist', 'option:simpleblacklist__blacklist', _t( 'Items to blacklist (words, IP addresses, URLs, etc):' ) );
					$frequency= $ui->append('select', 'frequency', 'option:simpleblacklist__frequency', _t( 'Bypass blacklist for frequent commenters:' ) );
					$frequency->options = array( '0' => 'No', '1' => 'Yes');
					$ui->append( 'submit', 'save', 'Save' );
					$ui->out();
				break;
			}
		}
	}
	
	public function filter_comment_insert_allow( $allow, $comment )
	{
		// don't blacklist logged-in users: they can speak freely
		if ( User::identify() ) { return true; }

		// and if the person has more than 5 comments approved,
		// they're likely not a spammer, so don't blacklist them
		$bypass= Options::get('simpleblacklist__frequency');
		if ( $bypass ) {
			$comments= Comments::get( array( 'email' => $comment->email,
			'name' => $comment->name, 
			'url' => $comment->url,
			'status' => Comment::STATUS_APPROVED )
			);
			if ( $comments->count >= 5 ) {
				return true;
			}
		}
	
		$allow= true;
		$blacklist= explode( "\n", Options::get('simpleblacklist__blacklist') );
		foreach ( $blacklist as $item ) {
			$item= trim(strtolower($item));
			if ( '' == $item ) { continue; }
			// check against the commenter name
			if ( false !== strpos( strtolower($comment->name), $item ) ) {
				$allow= false;
			}
			// check against the commenter email
			 if ( false !== strpos( strtolower($comment->email), $item ) ) {
			 	$allow= false;
			}
			// check against the commenter URL
			 if ( false !== strpos( strtolower($comment->url), $item ) ) {
			 	$allow= false;
			}
			// check against the commenter IP address
			 if ( false !== strpos( $comment->ip, $item ) ) {
			 	$allow= false;
			}
			// now check the body of the comment
			 if ( false !== strpos( strtolower($comment->content), $item ) ) {
			 	$allow= false;
			}
		}
		return $allow;
	}
}
?>
