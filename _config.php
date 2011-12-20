<?php
/**
 * @package silverstripe-buddy
 */


DataObject::add_extension('Member','BuddyMember');

//Set the url for managing buddies (call in site config to override)
Buddies::set_url('buddies');
//Set the url for searching for a buddy (call in site config to override)
BuddySearches::set_url('search');
//Set the url for seeing a buddy profile
BuddyProfile::set_url('profile');

// Check dependencies
if(!class_exists("TagField")) {
    user_error(_t('Messages.TAGFIELD','The Buddy module requires Tag Field module'),E_USER_ERROR);
}
