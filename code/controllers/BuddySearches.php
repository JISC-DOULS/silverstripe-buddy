<?php
/**
 * Create/View/Manage Searches.
 * Run a (new or stored) search
 * Invite users found in search
 *
 */
class BuddySearches extends Page_Controller {

    /**
     * @var string The URL segment that will point to this controller
     */
    public static $url_segment;
    private static $salt = '12lk[>%4rFZ';//Change this from site config
    public static $inviteprefix = 'invite_';

    /**
     * Maximum number of buddy search results queried and shown
     * @var int
     */
    public static $maxresults = 20;

    public static $allowed_actions = array(
        'index',
        'buddySearchForm',
        'find',
        'deleteForm',
        'saveSearchForm',
        'inviteForm',
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
            $url . '/$Action/$ID' => 'BuddySearches'
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
     * Main Screen display
     * @param SS_HTTPRequest $request
     */
    public function index(SS_HTTPRequest $request) {
        $messages = BuddyActionMessage::getMessages('search');
        $searches = '';
        $newform = '';
        if (Permission::check('ADD_A_BUDDY')) {
            $searches = BuddySearch::get_searches(Member::currentUserID());
            $newform = $this->buddySearchForm();
        } else {
            $messages = BuddyActionMessage::makeMessage(_t('Buddysearch.denied',
                'Sorry, You do not have permission to find a buddy.'));
        }
        return array(
            'Messages' => $messages,
            'searches' => $searches,
            'newform' => $newform
        );
    }

    /**
     * Form to delete a saved search
     * @return Form
     */
    public function deleteForm() {
        $fields = new FieldSet();
        $actions = new FieldSet(
            array(
                new FormAction('doDelete', _t('BUDDY.searchdelete', 'Delete'))
            )
        );
        return new Form($this, 'deleteForm', $fields, $actions);
    }

    /**
     * Deletes a users stored search
     * @param array $data
     * @param Form $form
     */
    public function doDelete(array $data, Form $form) {
        $id = (int) $data['id'];
        $error = false;
        if (BuddySearch::check_search_belongs($id, Member::currentUserID())) {
            BuddySearch::delete_search($id);
        } else {
            $error = true;
        }
        if ($error) {
            BuddyActionMessage::setMessage('search', _t('BUDDY.searchdelerror',
                'Error deleting search record'));
        } else {
            BuddyActionMessage::setMessage('search', _t('BUDDY.searchdelok',
                'Saved search deleted.', BuddyActionMessage::MESSAGE_SUCCESS));
        }
        $this->redirectBack();
    }

    public function buddySearchForm() {
        //Drop down options for study choice
        $studychoice = array(
            '0' => _t('BUDDY.searchsc_cur', 'Is currently studying'),
            '1' => _t('BUDDY.searchsc_pre', 'Has studied'),
            '2' => _t('BUDDY.searchsc_ethr', 'Either'),
        );
        //Interests
        $source = DataObject::get('BuddyInterests', null, 'Title ASC');
        $sourcemap = $source ? $source->toDropdownMap('ID', 'Title') : array();
        //Interest drop down
        $interestchoice = array(
            '0' => _t('BUDDY.searchint_none', 'None'),
            '1' => _t('BUDDY.searchint_any', 'Any'),
            '2' => _t('BUDDY.searchint_all', 'All'),
            '3' => _t('BUDDY.searchint_onlyall', 'Only all'),
        );
        //$tagField = new TagField('BuddyStudied', null, null, 'Member');
        //$tagField->setCustomTags(array('mytag','myothertag'));
        $fields = new FieldSet(
            array(
                //$tagField,
                new StudyChoiceField('study', _t('BUDDY.searchstudy', 'Study choice')),
                new DropdownField('studydrop', _t('BUDDY.searchstudydrop', 'Match study choice'), $studychoice),
                new CheckboxSetField('interests', _t('BUDDY.search', 'Subjects'), $sourcemap),
                new DropdownField('interestdrop',
                    _t('BUDDY.interestdrop', 'Has an interest in subjects selected'), $interestchoice),
                new DropdownField('helpdrop',
                    _t('BUDDY.helpdrop', 'Can help with subjects selected'), $interestchoice),
            )
        );
        $actions = new FieldSet(array(
            new FormAction('find', _t('BUDDY.searchgo', 'Find a buddy'))
        ));
        return new Form($this, 'buddySearchForm', $fields, $actions);
    }

    /**
     * Function to search for a buddy
     * Called directly by url (for saved search) or from form submit
     * @param $data
     * @param $form
     */
    public function find($data = null, $form = null) {
        if (!Permission::check('ADD_A_BUDDY')) {
            return array(
                'Messages' => BuddyActionMessage::makeMessage(_t('Buddysearch.denied',
                    'Sorry, You do not have permission to find a buddy.'))
            );
        }
        $id = 0;
        if (!is_array($data)) {
            //Not from submit, from saved
            $id = (int) $this->request->param('ID');
            if ($id == 0 || !BuddySearch::check_search_belongs($id, Member::currentUserID())) {
                $msg = BuddyActionMessage::makeMessage('Invalid search id.');
                return array('Messages' => $msg);
            }
            //Get stored options //TODO - check if cached otherwise querying same record twice
            $rec = DataObject::get_by_id('BuddySearch', $id);
            $data = stripslashes($rec->Options);
            $data = unserialize(html_entity_decode($data));
            //Update record so last run (last edited) is now
            $rec->writeWithoutVersion();//For some reason standard write won't update record
        }

        /*
         * Attempt 1 at finding matches
         * //TODO optimise and put in to 1 query
         */
        $results = new BuddyResultDO();

        //1. Get studying or studied matches
        if (!empty($data['study'])) {
            //Study choices are space separated in db
            if ($data['studydrop'] == 0 || $data['studydrop'] == 2) {
                if ($studying = StudyChoiceField::get_matching_members(Member::currentUserID(),
                    'BuddyStudying', $data['study'])) {
                    $results->merge($studying);
                }
            }
            if ($data['studydrop'] == 1 || $data['studydrop'] == 2) {
                if ($studied = StudyChoiceField::get_matching_members(Member::currentUserID(),
                    'BuddyStudied', $data['study'])) {
                    $results->merge($studied);
                }
            }
        }
        //2. Interests/help with
        if (isset($data['interests']) && ($data['interestdrop'] != 0 || $data['helpdrop'] != 0)) {
            if ($data['interestdrop'] >= 1) {
                if ($intres = BuddyInterests::search_matching(Member::currentUserID(),
                        $data['interests'], 'Member_BuddyInterest', $data['interestdrop'] - 1)) {
                    $results->merge($intres);
                }
            }
            if ($data['helpdrop'] >= 1) {
                if ($intres = BuddyInterests::search_matching(Member::currentUserID(),
                        $data['interests'], 'Member_BuddyCanHelp', $data['helpdrop'] - 1)) {
                    $results->merge($intres);
                }
            }
        }

        /**
         * Extension this class and add a findMore method to add in extra member matches
         * These will be merged with standard results
         * http://www.silverstripe.org/archive/show/4199
         */
        if (in_array('findMore', $this->allMethodNames(true))) {
            $extra = $this->findMore($data);
            if ($extra instanceof DataObjectSet) {
                $results->merge($extra);
            }
        }
        $results->removeResultDuplicates();
        $results->sort('Surname', 'DESC');
        $results->sort('FirstName', 'DESC');
        $results->sort('Matching', 'DESC');
        //Return a sub set of 'top' matches
        $results = $results->getRange(0, self::$maxresults);
        //Show save form if not already a saved search
        $form = '';

        if ($id == 0) {
            $hiddendata = htmlentities(serialize($data), ENT_COMPAT, 'utf-8', false);
            $form = $this->saveSearchForm($hiddendata);
        }
        return array('found' => $results, 'saveasearch' => $form, 'isfind' => 1);
    }

    public function saveSearchForm($options = null) {
        $fields = new FieldSet(array(
            new TextField('name', _t('BUDDY.searchsavename', 'Name'), '', 25),
            new HiddenField('options', null, $options),
        ));
        $actions = new FieldSet(array(
            new FormAction('saveSearch', _t('BUDDY.searchsave', 'Save search criteria')),
        ));
        return new Form($this, 'saveSearchForm', $fields, $actions);
    }

    /*
     * Action to search for suggestions on study choice
     */
    public function suggest($request) {
        if (Director::is_ajax()) {
            //ReCreate field object (as we want from 2 fields, name is overridden anyway)
            $field = new StudyChoiceField('BuddyStudied', null, null, 'Member');
            return $field->combinedsuggest($request);
        } else {
            return '';
        }
    }

    /**
     * Saves the search options
     * @param $data
     * @param $form
     */
    public function saveSearch(array $data, Form $form) {
        $options = html_entity_decode($data['options']);
        //$options = Convert::raw2sql($options);
        $fields = array(
            'Options' => $options,
            'InitiatorID' => Member::currentUserID()
        );
        //Add name if not blank: otherwise default in datobject will be used
        if (!Empty($data['name'])) {
            $fields['Name'] = strip_tags($data['name']);
        }
        $search = new BuddySearch($fields);
        $search->write();
        if (!Director::is_ajax()) {
            $this->redirect($this->Link('index'));
        } else {
            return new SS_HTTPResponse('saved', 200);
        }
    }

    /**
     * Form used to create an invite
     * Text message only added in this form - id's must be added in template
     */
    public function inviteForm() {
        $fields = new FieldSet(array(
            new TextareaField('invitemsg', _t('BUDDY.search_invitemsg', 'Invite message'), 12),
            new LiteralField('invitevalmsg', '<label for="Form_inviteForm_invitemsg" style="display:none">' .
                _t('BUDDY.search_invitemsg', 'Invite message').'</label>')
        ));
        $actions = new FieldSet(array(
            new FormAction('invite', _t('BUDDY.search_invitesub', 'Invite'))
        ));
        $validator = new RequiredFields('invitemsg');
        return new Form($this, 'inviteForm', $fields, $actions, $validator);
    }

    /**
     * Submit an invitation request
     * @param $data
     * @param $form
     */
    public function invite(array $data, Form $form) {
        //TODO - need to double check permission that user can add a buddy?
        $success = true;
        $errors = 0;
        $numinvites = 0;
        //Get all member buddies in form
        foreach($data as $key => $value) {
            if (strpos($key, self::$inviteprefix) === 0) {
                $id = intval(substr($key, strlen(self::$inviteprefix)));
                if ($value == self::inviteHash($id)) {
                    $numinvites++;
                    //User hasn't spoofed request - call buddy add code (this checks data etc)
                    if (!Buddy::inviteRequest($id, $data['invitemsg'])) {
                        $success = false;
                        $errors++;
                    }
                }
            }
        }
        //Add a feedback message
        if ($numinvites > 0 && $success) {
            BuddyActionMessage::setMessage('search', _t('BUDDY.inviteok', 'Invitations sent successfully.'),
                BuddyActionMessage::MESSAGE_SUCCESS);
        } else {
            if ($numinvites == 0) {
                $msg = _t('BUDDY.invitefailnone', 'No users selected for invitation.');
            } else {
                $msg = _t('BUDDY.invitefail', 'Failed to send %d invitations.');
                $msg = sprintf($msg, $errors);
            }
            BuddyActionMessage::setMessage('search', $msg);
        }
        //Redirect to search or buddies?
        $this->redirect($this->Link('index'));
    }

    public function getinviteprefix() {
        return self::$inviteprefix;
    }

    public static function inviteHash(int $m1, $m2 = null) {
        if (is_null($m2)) {
            $m2 = Member::currentUserID();
        }
        return hash('sha256', $m1 . self::$salt . $m2);
    }

}

/**
 * Extend data object set for buddy search results
 */
class BuddyResultDO extends DataObjectSet {
    /**
     * Remove duplicate results (based on ID)
     * Add the Matching column from all duplicates to master record
     */
    public function removeResultDuplicates() {
        $exists = array();
        foreach($this->items as $key => $item) {
            if(isset($exists[$fullkey = ClassInfo::baseDataClass($item) . ":" . $item->ID])) {
                if (!empty($item->Matching)) {
                    //Set existing match to have Matching column added to this
                    $this->items[$exists[$fullkey]]->Matching += $item->Matching;
                }
                unset($this->items[$key]);
            } else {
                $exists[$fullkey] = $key;
            }
        }
    }
}
