<div id="slickLeftBlock">
	<div id="slickLeftBlockPad">
		<a href="{$home_link}">{$home_image}</a>
		<a onclick="openPopup(this, 300, 400, true, 'Slimzora'); return false;" href="{$slimzora_link}" target="_blank">{$img_slim_pop}</a>
		{if $help_access == "true" or $help_access == "all" or ($help_access == "admin" and $is_admin == true)}
			<a onclick="openPopup(this, 640, 480, true, 'Docs'); return false;" href="{$docs_link}" target="_blank">{$img_docs}</a>
		{/if}
		{if $is_admin == true}
		<a onclick="openPopup(this, 500, 400, true, 'Tools'); return false;" href="{$admin_tools_link}" target="_blank">{$img_tools}</a>
		{/if}
		<br />
		<strong>
			{$word_user}: {$user_name}
		</strong>
		<br />
		{$login_link}
		{if $edit_prefs == true && $login_link != ""} | {/if}
		{if $edit_prefs == true}
			<a onclick="openPopup(this, 300, 350, true, 'Preferences'); return false;" href="{$pref_link}">Prefs</a>
		{/if}
	</div>
</div>
<div id="slickLeftBlockSpace"></div>