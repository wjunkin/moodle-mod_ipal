moodle-mod_ipal
===============

This version of IPAL (release = '2.2.1 (Build: 2017101900)') is compatible with Moodle 2.7, 2.8, 2.9, 3.0, 3.1, 3.2, and 3.3. 
It will not run in Moodle 2.6.
The directory ipal should be placed in the moodle mod folder.
It supports in-class polling when students use web-enabled devices and/or smart phones with free Apps. 
There is a polling App for the iPhone and an App for Android devices. 
This version includes a utility to easily take attendance using ipal and the Moodle attendance module (https://moodle.org/plugins/view/mod_attendance). We have been assisted in its development by the Moodle support team at the Hebrew University of Jerusalem.
This version allows user names with non-alphanumeric characters.
This version no longer supports the EJS module. (This is a temporary change.)
This version supports Firebase Cloud Messaging for the new version of the IPAL Android App.
Firebase refreshes the student screen in the App whenever the teacher sends a question or stops polling.
This version has also removed the use of $_GET in several files.