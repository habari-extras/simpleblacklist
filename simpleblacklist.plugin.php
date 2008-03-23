<?php

class SimpleBlacklist extends Plugin
{
	const VERSION = '1.0';
	
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
					$blacklist= $ui->add( 'textarea', 'blacklist', 'Items to blacklist (words, IP addresses, URLs, etc):' );
					$frequency= $ui->add('select', 'frequency', 'Bypass blacklist for frequent commenters:');
					$frequency->options = array( '0' => 'No', '1' => 'Yes');
					$ui->on_success( array( $this, 'updated_config' ) );
					$ui->out();
				break;
			}
		}
	}
	
	public function updated_config( $ui )
	{
		return true;
	}

	public function filter_comment_insert_allow( $allow, $comment )
	{
		// don't blacklist logged-in users: they can speak freely
		if ( User::identify() ) { return true; }

		// and if the person has more than 5 comments approved,
		// they're likely not a spammer, so don't blacklist them
		$bypass= Options::get('SimpleBlacklist:frequency');
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
		$blacklist= split( "\n", Options::get('SimpleBlacklist:blacklist') );
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
