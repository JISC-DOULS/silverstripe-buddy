<?php
/**
 * View/Manage Buddies.
 * 1. Show lists of:
 *  Invites that need responding to
 *  Your Invites that you need to respond to
 *  List of Buddies (sortable, paginate?) table
 */
class Buddies extends Page_Controller {

    /**
     * @var string The URL segment that will point to this controller
     */
    public static $url_segment;

    public static $allowed_actions = array(
        'index',
        'delete',
        'deleteForm',
        'viewimsg',
        'inviteActionForm'
    );

    public static $buddies_per_page = 20;

    public function init() {
        parent::init();
        $redirectalready = $this->response->getHeader('Location');
        //You must be logged in as this feature for members only
        if (!Member::logged_in_session_exists() && empty($redirectalready)) {
            Director::redirect("Security/login?BackURL=" . urlencode($_SERVER['REQUEST_URI']));
        }
        //Requirements (js/css)
        Requirements::javascript(THIRDPARTY_DIR.'/jquery/jquery.js');
        Requirements::javascript(THIRDPARTY_DIR.'/jquery-livequery/jquery.livequery.js');
        Requirements::javascript(THIRDPARTY_DIR.'/jquery-metadata/jquery.metadata.js');
        Requirements::javascript('dataobject_manager/javascript/facebox.js');
        Requirements::javascript('buddy_message/javascript/validation.js');
        Requirements::javascript('buddy_message/javascript/validation_improvements.js');
        Requirements::css('dataobject_manager/css/facebox.css');
        Requirements::javascript('buddy_message/javascript/jquery.fcbkcomplete.js');
        Requirements::javascript('buddy_message/javascript/jquery.form.js');
        Requirements::javascript('buddy_message/javascript/jquery.scrollTo.js');
        Requirements::css('buddy_message/css/jquery.fcbkcomplete.css');
        Requirements::javascript('buddy_message/javascript/behaviour.js');
        Requirements::themedCSS('messages');
    }

    /**
     * Set the url for this controller and register it with {@link Director}
     * @param string $url The URL to use
     * @param $priority The priority of the URL rule
     */
    public static function set_url($url, $priority = 50) {
        self::$url_segment = $url;
        Director::addRules($priority,array(
            $url . '/$Action/$ID' => 'Buddies'
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
     * Standard page functionality (1)
     * @param SS_HTTPRequest $request
     */
    public function index(SS_HTTPRequest $request) {
        //Get all user buddies
        $start = (int) $request->getVar('start');
        if (!isset($start)) {
            $start = 0;
        }
        $total = self::$buddies_per_page;
        if ($request->getVar('all')) {
            $total = null;//get all
        }
        $memberbuddies = Buddy::getMemberBuddies(Member::currentUserID(), $total, $start);

        //Get all requests user has sent that have not been replied to
        $memberinvites = Buddy::getMemberNoResponds(Member::currentUserID());
        $memberinvited = null;
        //Can only see invites when have permission
        if (Permission::check('ADD_AS_BUDDY')) {
            $memberinvited = Buddy::getMemberToResponds(Member::currentUserID());
        }

        $messages = BuddyActionMessage::getMessages('buddies');
        return array(
            'Buddies' => $memberbuddies,
            'NoResponses' => $memberinvites,
            'NotResponded' => $memberinvited,
            'Messages' => $messages
        );
    }

    /**
     * Delete buddy page
     * @param $request
     */
    public function delete(SS_HTTPRequest $request) {
        $buddyid = (int) $request->param('ID');
        //When the form gets rebuilt in post it will fall over without subbing param for post var
        $postvars = $request->postVars();
        if (empty($buddyid) && isset($postvars['usertodelete'])) {
            $buddyid = (int) $postvars['usertodelete'];
        }

        //Check get specified user
        try {
            if (!$buddy = DataObject::get_one("Member", "\"Member\".\"ID\" = $buddyid")) {
                throw new exception();
            }
            $buddyname = $buddy->getName();
            //Check these are buddies
            if (!Buddy::getAreBuddies($buddyid, Member::currentUserID(), Buddy::RELATIONSHIP_CONFIRMED)) {
                throw new exception();
            }
        } catch (Exception $e) {
            //Show error message
            $errormsg = _t('BUDDY.RemoveNotBuddyError', 'The user specified is not a valid buddy.');
            return array('delete' => '', 'Messages' => BuddyActionMessage::makeMessage($errormsg));
        }

        return array('delete' => $this->deleteForm($buddyid, $buddyname, $request));
    }

    public function deleteForm($buddyid = '', $buddyname = '', $request = '') {
        if (!empty($request)) {
            $request = $request->getHeader('Referer');
        } else {
            $request = $this->Link();
        }
        $inst = "Are you sure you wish to remove $buddyname from your Buddy list?";
        $fields = new FieldSet(
            new HiddenField('usertodelete', null, $buddyid),
            new HiddenField('_REDIRECT_BACK_URL', null, $request),
            new LiteralField('Instructions', '<p>' . _t('BUDDY.RemoveInst', $inst) . '</p>')
        );
        $actions = new FieldSet(
            new FormAction('doDelete', _t('Buddy.DELETE','Delete')),
            new FormAction('doCancel', _t('Buddy.CANCEL','Cancel'))
        );
        return new Form($this, 'deleteForm', $fields, $actions);
    }

    /**
     * Handle the action for cancelling a form
     * @param array $data The form data that was passed
     * @param Form $form The form that was used
     * @return SS_HTTPResponse
     */
    public function doCancel($data, $form) {
        $this->redirect($data['_REDIRECT_BACK_URL']);
    }

    /**
     * Ends a buddy relationship
     * @param Array $data
     * @param Form $form
     */
    public function doDelete($data, $form) {
        try {
            Buddy::deleteBuddy($data['usertodelete'], Member::currentUserID());
        } catch (Exception $e) {
            BuddyActionMessage::setMessage('buddies', $e->getMessage());
        }
        $this->redirect($data['_REDIRECT_BACK_URL']);
    }

    /**
     * View invite message page
     * @param $request
     */
    public function viewimsg(SS_HTTPRequest $request) {
        $relid = (int) $request->param('ID');
        if(!$msgrec = Buddy::getAInviteMessage($relid, Member::currentUserID())) {
            //Show error message
            $errormsg = _t('BUDDY.ViewIMsgError', 'It is not possible to view this message.');
            return array('msg' => '', 'Messages' => BuddyActionMessage::makeMessage($errormsg));
        }
        $content = $msgrec->InviteMessage;
        //$content = str_replace(array('\\r', '\\n'), array("\r", "\n"), $content);
        $content = Convert::raw2xml($content);
        $content = stripslashes($content);
        return array('msg' => $msgrec, 'content' => $content);
    }

    public function inviteActionForm() {
        $fields = new FieldSet();
        $actions = new FieldSet(
            new FormAction('doAccept', _t('Buddy.ACCEPT','Accept')),
            new FormAction('doReject', _t('Buddy.REJECT','Reject'))
        );
        return new Form($this, 'inviteActionForm', $fields, $actions);
    }

    /**
     * Accepts a buddy invite (that someone has sent user)
     * @param $data
     * @param Form $form
     */
    public function doAccept($data, $form) {
        if($accept = Buddy::updateBuddyInvite($data['id'], Member::currentUserID(),
            Buddy::RELATIONSHIP_CONFIRMED)) {
            $successmsg = $accept->getInitiatorName();
            $successmsg .= _t('BUDDY.buddyaccepted', ' has been accepted as your Buddy');
            BuddyActionMessage::setMessage('buddies', $successmsg, BuddyActionMessage::MESSAGE_SUCCESS);
        } else {
            BuddyActionMessage::setMessage('buddies', 'Not a valid action.');
        }
        $this->redirectBack();
    }

    /**
     * Reject a buddy invite (that someone has sent user)
     * @param $data
     * @param Form $form
     */
    public function doReject($data, $form) {
        if($accept = Buddy::updateBuddyInvite($data['id'], Member::currentUserID(),
            Buddy::RELATIONSHIP_REJECTED)) {
            $successmsg = $accept->getInitiatorName();
            $successmsg .= _t('BUDDY.buddyrejected', ' has been rejected as your Buddy');
            BuddyActionMessage::setMessage('buddies', $successmsg, BuddyActionMessage::MESSAGE_SUCCESS);
        } else {
            BuddyActionMessage::setMessage('buddies', 'Not a valid action.');
        }
        $this->redirectBack();
    }
}
