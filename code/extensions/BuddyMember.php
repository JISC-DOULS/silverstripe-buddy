<?php
class BuddyMember extends DataObjectDecorator {

    //Used to store a list of buddies so that this can be checked without db call
    private static $budy_list_data;

    /**
     * Update the database fields
     * @return array
     */
    public function extraStatics() {
        return array (
            'has_many' => array (
                'BuddySearches' => 'BuddySearch'
            ),
            'many_many' => array (
                'BuddyInterest' => 'BuddyInterests',
                'BuddyCanHelp' => 'BuddyInterests'
            ),
            'db' => array (
                'BuddyPublicAvatar' => 'Boolean',
                'BuddyPublicProfile' => 'Boolean',
            	'BuddyEmailToggle' => 'Boolean',
                'BuddyAboutMe' => 'Varchar(255)',
                'BuddyThinkingAbout' => 'Varchar(255)',
                'BuddyLearningAbout' => 'Varchar(255)',
                'BuddyStudied' => 'Text',
                'BuddyStudying' => 'Text',
            )
        );
    }

    /**
     * Set our new db fields defaults
     */
    public function populateDefaults() {
        $this->owner->BuddyPublicAvatar = true;
        $this->owner->BuddyPublicProfile = false;
        $this->owner->BuddyEmailToggle = false;
    }

    /**
     * Will return info on what fields the members buddy profile consists of
     * An array (in order required) with an element for each field
     * Names of the elements should correspond to db fields in Member
     * Fields defined as Class BuddyMemberProfileField
     * @return Array
     */
    public function getProfileStructure() {
        $return = array();
        $return['BuddyPublicAvatar'] = new BuddyMemberProfileField(
            _t('BUDDY.profileavatar', 'Let non-buddies see your avatar'),
            'BuddyPublicAvatar',
            null,
            'CheckboxField'
        );
        $return['BuddyPublicProfile'] = new BuddyMemberProfileField(
            _t('BUDDY.profilepublic', 'Let non-buddies see your profile'),
            'BuddyPublicProfile',
            null,
            'CheckboxField'
        );
        //Uncomment to enable option to send messages as email
        /*$return['BuddyEmailToggle'] = new BuddyMemberProfileField(
              _t('BUDDY.profileemailtoggle', 'Allow the system to send you emails'),
              'BuddyEmailToggle',
              null,
              'CheckboxField'
        );*/
        $return['BuddyAboutMe'] = new BuddyMemberProfileField(
            _t('BUDDY.profileaboutme', 'About me')
        );
        $return['BuddyThinkingAbout'] = new BuddyMemberProfileField(
            _t('BUDDY.profilethinkingabout', 'Thinking about')
        );
        $return['BuddyLearningAbout'] = new BuddyMemberProfileField(
            _t('BUDDY.profilelearningabout', 'Learning about')
        );
        $return['BuddyStudied'] = new BuddyMemberProfileField(
            _t('BUDDY.profilestudied', 'Have studied'),
            'BuddyStudied', 'default', 'StudyChoiceField'
        );
        $return['BuddyStudying'] = new BuddyMemberProfileField(
            _t('BUDDY.profilestudying', 'Are studying'),
            'BuddyStudying', 'default', 'StudyChoiceField'
        );
        $return['BuddyInterest'] = new BuddyMemberProfileField(
            _t('BUDDY.profileinterest', 'Interests'),
            'BuddyInterests', 'ManyManyCheckboxSet', 'CheckboxSetField'
        );
        $return['BuddyCanHelp'] = new BuddyMemberProfileField(
            _t('BUDDY.profilecanhelp', 'Can help with'),
            'BuddyInterests', 'ManyManyCheckboxSet', 'CheckboxSetField'
        );
        //Decorate this class and add an alternate method if own member fields required
        $me = $this->owner;
        if (in_array('alternateprofilestructure', $me->allMethodNames(true), true)) {
            return $me->alternateProfileStructure($return);
        }
        return $return;
    }

    //Add form fields to CMS so profile can be updated by others
    public function updateCMSFields(FieldSet &$fields)
    {
        $fields->removeByName('BuddyInterest');
        $fields->removeByName('BuddyCanHelp');
        //Remove from Main and add to a new Buddy tab
        $buddyprofile = $this->owner->getProfileStructure();
        foreach ($buddyprofile as $fieldname => $field) {
            if ($field->ftype != null) {
                $newfield = BuddyProfile::getFieldFromData($fieldname, $field);
                $fields->addFieldToTab('Root.Buddy', $newfield);
            }
        }

        //Show Users Buddies from DB in a table
        $curmem = $this->owner->ID;
        $fields->addFieldsToTab('Root.Buddies', new ComplexTableField($this, 'Buddy', 'Buddy',
            null, null, "InitiatorID = $curmem OR BuddyID = $curmem"));
    }

    //Member functions
    //Profile functions to...
    //Link to Message (Check if Buddy and check postale)
    //Link to Invite as buddy? (check if not already buddy, will also need some sort of security to stop you from altering to alternate id)
    //Link to User profile (MemberProfiles mod + If they've chosen to let others see?)

    /**
     * Returns a link to remove the owner from buddy list
     * (There is a confirmation form on that page)
     * @return String
     */
    public function getRemoveBuddylink() {
        return Controller::join_links(Buddies::$url_segment, 'delete', $this->owner->ID);
    }

    /**
     * Returns a link to Message the User (postale must be installed)
     * Does not check Buddy status - so must only be called when you know there
     * is a valid relationship in place
     * @return String
     */
    public function getBuddyNewMessageLink() {
        if (class_exists('BuddyMessagesPage')) {
            $url = BuddyMessagePage::$url_segment;
        } else if (class_exists('MessagesPage')) {
            //PostalInstalled
            return $this->owner->SendMessageLink();
        } else {
            return '';
        }
        return $url . '/add/?to=' . $this->owner->ID;
    }
    /**
     * Returns the Avatar of member we are dealing with
     * Checks settings and buddy status if needed
     * @param Member $member
     * @return Image
     */
    public function getBuddyAvatar() {
        $show = $this->owner->BuddyPublicAvatar;
        if ($this->owner->ID == Member::currentUserID()) {
            $show = true;//always show to self
        }
        if (!$show) {
            //User wants it hidden to public - is current user any type of buddy?
            if (!isset(self::$budy_list_data)) {
                //Store the current member's buddies in static
                self::$budy_list_data = Buddy::getMemberBuddies(Member::currentUserID());
            }
            if (self::getAreBuddies(self::$budy_list_data, $this->owner->ID)) {
                $show = true;
            }
            /*if (Buddy::getAreBuddies($this->owner->ID, Member::currentUserID())) {
                $show = true;
            }*/
        }
        return $this->getBuddyAvatarImage($show);
    }

    public function getBuddyAvatarImage($showactual = true) {
        $image = null;
        if (class_exists('MessagesMember') && $showactual) {
            //Postale is installed - use that
           if ($this->owner->AvatarID) {
               //They have specified an avatar
               $image = $this->owner->Avatar()->CroppedImage(50,50);
           }
        }

        //Default Buddy image (can't resize these so must match size above)
        if ($image == null) {
            $image = new Image_Cached('buddy/images/no_avatar.jpg');
        }
        return $image;
    }

    /**
     * Return the link to the profile page of the member
     * Does not do any checking here against permissions
     */
    public function getProfileLink() {
        return Controller::join_links(BuddyProfile::$url_segment, 'index', $this->owner->ID);
    }

    /**
     * Checks if we are refering to current member
     * (To call in DataObjectSet)
     * @return Boolean
     */
    public function isCurrentMember() {
        if($this->owner->ID == Member::currentUserID()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns a hash of the user so you can't try and buddy up with random member ID's
     */
    public function inviteHash() {
        return BuddySearches::inviteHash($this->owner->ID);
    }

    /**
     * Taking a data object of the current user's buddies
     * will return if another user id is in list
     * @param DataObject $do
     * @param int $ownerid
     * @return Boolean
     */
    public static function getAreBuddies(DataObject $do, int $ownerid) {
        foreach ($do as $record) {
            if (($record->InitiatorID == $ownerid && $record->BuddyID == Member::currentUserID())
                || ($record->BuddyID == $ownerid && $record->InitiatorID == Member::currentUserID())) {
                    return true;
            }
        }
        return false;
    }
}

/**
 * Definition of profile field structure
 */
class BuddyMemberProfileField {
    public $name;//Display name/label
    public $dbfield;//Set to use alternate dbfield from array element name
    public $vtype;//View type e.g. default - as supported in template (null = don't show)
    public $ftype;//Form field type (class name) (null = don't show)

    public function __construct($name, $dbfield = null, $vtype = 'default', $ftype = 'TextField') {
        $this->name = $name;
        $this->dbfield = $dbfield;
        $this->vtype = $vtype;
        $this->ftype = $ftype;
    }
}
