<?php


class Spaces_Invitation_Markup {
	public static function notification_button( $d ) {
		return "
			<div class='cell shrink notification-status {$d['class']}' data-blog-id='{$d['blog_id']}'>
				<span class='button expanded success notification-toggle' title='{$d['title']}'>
					<label for='notification-toggle-join-{$d['blog_id']}'><i class='fa fa-envelope'></i></label>
					<input
						id='notification-toggle-join-{$d['blog_id']}'
						data-blog-id='{$d['blog_id']}'
						class='switch-input'
						type='checkbox'
						name='notification-toggle[]'
						{$d['checked']}
					>
				</span>
			</div>
		";
	}

	public static function notification_toggle( $d ) {
		return "
			<div class='cell card static padding-button' id='notification-toggle-switch'>
				<label class='icon-left label-wrapper' for='notification-toggle-join-{$d['blog_id']}'>
					<i class='fa fa-envelope' aria-hidden='true'></i>
					<span>{$d['title']}</span>
					<div class='switch small notification-toggle success' title='{$d['title']}'>
						<input
							id='notification-toggle-join-{$d['blog_id']}'
							data-blog-id='{$d['blog_id']}'
							class='switch-input'
							type='checkbox'
							name='notification-toggle[]'
							{$d['checked']}
						>
						<label class='switch-paddle' for='notification-toggle-join-{$d['blog_id']}'>
							<span class='show-for-sr'><span>{$d['title']}</span></span>
						</label>
					</div>
				</label>
			</div>
		";
	}


}
