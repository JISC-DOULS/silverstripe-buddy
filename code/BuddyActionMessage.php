<?php
/**
 * Stores/retrieves messages across pages to display in a template
 * @author j.platts@open.ac.uk
 *
 */
class BuddyActionMessage {
    //css class names applied to message
    const MESSAGE_ERROR = 'error';
    const MESSAGE_WARNING = 'warning';
    const MESSAGE_SUCCESS = 'success';
    const MESSAGE_NAME = 'BuddyActionMessages';

    /**
     * Set a session wide message agains a specific 'page'
     * @param String $pagefor
     * @param String $text
     * @param Int $type
     */
    public static function setMessage($pagefor, $text, $type = self::MESSAGE_ERROR) {
        $sessionvars = array();
        if (Session::get(self::MESSAGE_NAME)) {
            $sessionvars = Session::get(self::MESSAGE_NAME);
        }
        $sessionvars[] = array(
            'page' => $pagefor,
            'text' => Convert::raw2xml($text),
            'type' => $type
        );
        Session::set(self::MESSAGE_NAME, $sessionvars);
    }

    /**
     * Goes through stored messages for 'page' - will only return the latest
     * Will remove all messages in session for page
     * @param String $pagefor
     * @Return DataArray or empty String
     */
    public static function getMessages($pagefor) {
        $pagemessage = '';
        $sessionvars = array();
        $newsessionvars = array();
        if (Session::get(self::MESSAGE_NAME)) {
            $sessionvars = Session::get(self::MESSAGE_NAME);
        }
        foreach ($sessionvars as $message) {
            if ($message['page'] == $pagefor) {
                $pagemessage = new ArrayData($message);
            } else {
                //only keep other messages
                $newsessionvars[] = $message;
            }
        }
        if (count($newsessionvars) == 0) {
            Session::clear(self::MESSAGE_NAME);
        } else {
            Session::set(self::MESSAGE_NAME, $newsessionvars);
        }
        return $pagemessage;
    }

    /**
     * Make message for same script (not stored in session)
     * @param String $text
     * @param Int $type
     * @Returns ArrayData
     */
    public static function makeMessage($text, $type = self::MESSAGE_ERROR) {
        $pagemessage = array(
            'text' => Convert::raw2xml($text),
            'type' => $type
        );
        return new ArrayData($pagemessage);
    }
}
