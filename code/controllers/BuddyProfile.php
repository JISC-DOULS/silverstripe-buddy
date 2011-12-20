<?php
/**
 * View/Edit Member Buddy Profile.
 * Includes basic site member info and buddy specific settings
 * Extend this class and overwrite this with addRule when using own profile fields
 *
 */
class BuddyProfile extends Page_Controller {

    /**
     * @var string The URL segment that will point to this controller
     */
    public static $url_segment;

    public static $allowed_actions = array(
        'index',
        'editProfileForm',
        'suggest'
    );

    public function init() {
        parent::init();
        $redirectalready = $this->response->getHeader('Location');
        //You must be logged in as this feature for members only
        if (!Member::logged_in_session_exists() && empty($redirectalready)) {
            Director::redirect("Security/login?BackURL=" . urlencode($_SERVER['REQUEST_URI']));
        }
        //Requirements (js/css)
    }

    /**
     * Set the url for this controller and register it with {@link Director}
     * @param string $url The URL to use
     * @param $priority The priority of the URL rule
     */
    public static function set_url($url, $priority = 50) {
        self::$url_segment = $url;
        Director::addRules($priority,array(
            $url . '/$Action/$ID' => 'BuddyProfile'
        ));
    }

    /**
     * Provide a link to this controller
     * @param string $action The action of the controller
     * @param string $id The ID property
     * @return string
     */
    public function Link($action = null, $id = null) {
        return Controller::join_links(self::$url_segment, $action, $id);
    }

    /**
     *
     * @param SS_HTTPRequest $request
     */
    public function index(SS_HTTPRequest $request) {

        $edit = false;
        try {
            if (!$userid = (int) $request->param('ID')) {
                $userid = Member::currentUserID();
                $edit = true;
            }
            //Check user exists
            if (!$member = DataObject::get_by_id('Member', $userid)) {
                throw new exception();
            }
            //Check if current user
            if (Member::currentUserID() == $member->ID) {
                $edit = true;
            } else {
                //Check Permission VIEW_BUDDY_PROFILE
                if(!Permission::check('VIEW_BUDDY_PROFILE')) {
                    throw new exception();
                }
                //If not current user check what availability user has set (Buddies or open access)
                if (isset($member->BuddyPublicProfile) && $member->BuddyPublicProfile == false) {
                    //Are users Buddies? - if not don't show profile
                    if (!Buddy::getAreBuddies($member->ID, Member::currentUserID(),
                        Buddy::RELATIONSHIP_CONFIRMED)) {
                        throw new exception();
                    }
                }
            }
        } catch(Exception $e) {
            $errormsg = _t('BUDDY.profiledeny', 'You are not allowed to access this profile.');
            return array('Messages' => BuddyActionMessage::makeMessage($errormsg));
        }
        $messages = BuddyActionMessage::getMessages('profile');

        if ($edit) {
            //Show user edit form
            return array('form' => $this->editProfileForm(), 'messages' => $messages);
        } else {
            //Show profile page
            return array('pdata' => $this->getProfileData($member));
        }
    }

    /**
     * Returns an array of viewable data objects for each field
     * name: display name
     * type: how to display (pick up in template)
     * value: string/object that should be displayed depending on type
     * @param Object $member
     * @return Array
     */
    public function getProfileData(Member $member) {
        $result = new DataObjectSet();
        //Standard user info
        //Avatar
        $result->push(new ArrayData(array(
            'name' => _t('BUDDY.profileavatar', 'Avatar'),
            'type' => 'default',
            'value' => $member->getBuddyAvatar()
            ))
        );
        //Member name
        $result->push(new ArrayData(array(
            'name' => _t('BUDDY.profilename', 'Name'),
            'type' => 'default',
            'value' => Convert::raw2xml($member->getName())
            ))
        );
        //Buddy settings
        $buddyprofile = $member->getProfileStructure();
        foreach ($buddyprofile as $fieldname => $field) {
            //View type set?
            if ($field->vtype != null) {
                //Get data value, either from record or work out
                $value = null;
                if (!is_null($field->dbfield)) {
                    //Specific field identified
                    $dbfield = $field->dbfield;
                    $value = Convert::raw2xml($member->$dbfield);
                } else {
                    //Noting identified - is element name a field?
                    if (!empty($member->$fieldname)) {
                        $value = Convert::raw2xml($member->$fieldname);
                    }
                }
                //Different types might need to get value differently
                if (stripos($field->vtype, 'ManyMany') !== false) {
                    //Need to get many many values (using array element name)
                    //TODO - How can titles be multi-language?
                    $value = $member->getManyManyComponents($fieldname, null, 'Title ASC');
                }
                $result->push(new ArrayData(array(
                    'name' => $field->name,
                    'type' => $field->vtype,
                    'value' => $value
                    ))
                );
            }
        }
        return $result;
    }

    /**
     * Edit profile form
     * @return Form
     */
    public function editProfileForm() {
        $member = Member::currentUser();
        $buddyprofile = $member->getProfileStructure();

        $fields = new FieldSet();
        //Standard user info
        $fields->push(new ReadonlyField('name', _t('BUDDY.profilename', 'Name'),
            $member->getName()));
        $imagefield = new SimpleImageField('Avatar', _t('BUDDY.profileavatar', 'Avatar'));
        upload_Validator::setAllowedMaxFileSize(array('*' => 1048576));
        //$imagefield->setAllowedMaxFileSize(array('*' => 1048576));//Max size 1MB
        $fields->push($imagefield);

        foreach ($buddyprofile as $fieldname => $field) {
            if ($field->ftype != null) {
                $newfield = self::getFieldFromData($fieldname, $field);
                $fields->push($newfield);
            }
        }

        $actions = new FieldSet(
            new FormAction('saveProfile', _t('BUDDY.saveprofile', 'Update profile'))
        );

        $Form = new Form($this, 'editProfileForm', $fields, $actions);
        $Form->loadDataFrom($member->data());
        return $Form;
    }

    /**
     * Given data from a BuddyMemberProfileField object return a form field
     * Certain types that are supported have extra data sent so can not use
     * a genric call using the ftype property
     * @param $fieldname
     * @param $field BuddyMemberProfileField
     * @return FormField
     */
    public static function getFieldFromData(string $fieldname, BuddyMemberProfileField $field) {
        //Handle special cases we know about
        switch ($field->ftype) {
            case 'TextField':
                //Apply limit to text fields as we assume max varchar len
                return new TextField($fieldname, $field->name, null, 255);
            break;
            case 'CheckboxSetField':
                //Source is table in dbfield - should have ID + Title
                $source = DataObject::get($field->dbfield, null, 'Title ASC');
                $sourcemap = $source ? $source->toDropdownMap('ID', 'Title') : array();
                //TODO - How can titles be multi-language?
                return new CheckboxSetField($fieldname, $field->name, $sourcemap);
            break;
            case 'StudyChoiceField':
                return new StudyChoiceField($fieldname, $field->name, null, 'Member');
            break;
            default:
                //Return type specified with name and title only
                return new $field->ftype($fieldname, $field->name);
            break;
        }
    }

    /*
     * Action to search for suggestions on study choice
     */
    public function suggest($request) {
        if (Director::is_ajax()) {
            $id = Convert::raw2sql($this->request->param('ID'));
            $field = new StudyChoiceField($id, null, null, 'Member');
            return $field->suggest($request);
        } else {
            return '';
        }
    }

    /**
     * Saves the user's profile data from form
     * @param Array $data
     * @param Form $form
     */
    public function saveProfile($data, $form) {

        if ($currentMember = Member::currentUser()) {

            $form->saveInto($currentMember);

            $currentMember->write();
            BuddyActionMessage::setMessage('profile', _t('Buddy.profilesuccess', 'Profile updated'));
        } else {
            BuddyActionMessage::setMessage('profile', _t('Buddy.profilefail', 'Action not allowed'));
        }

        $this->redirectBack();
    }
}
