<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ifsnop\Mysqldump\Mysqldump;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SuperAdminController extends BaseController {

  public function __construct(ManagerRegistry $doctrine) {
    parent::__construct($doctrine);
  }

  protected function render(string $template, array $args = [], Response $response = null) : Response {
    $args['user'] = $this->getUser();
    return parent::render($template, $args, $response);
  }

  #[Route('/superadmin/export',
    methods: ['GET']
  )]
  public function exportDatabaseAction(Request $request) {
    $username = $this->getParameter('app.db_user');
    $password = $this->getParameter('app.db_password');
    $host = $this->getParameter('app.db_host');
    $port = $this->getParameter('app.db_port');
    $dbname = $this->getParameter('app.db_name');

    $tmpfilebasename = $dbname."_backup.".date(DATE_ATOM);
    $tmpfile = tempnam("/tmp", $tmpfilebasename);

    $dump = new Mysqldump("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $dump->start($tmpfile);

    $response = new BinaryFileResponse($tmpfile);
    $response->headers->set("Content-Disposition",
      HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,
      $tmpfilebasename));
    $response->deleteFileAfterSend(true);

    return $response;
  }

  private function convertInputToMask($input) {
    if(is_array($input)) {
      $mask = 0;
      foreach($input as $intValue) {
        $mask += (int) $intValue;
      }
    } else {
      $mask = (int) $input;
    }
    return $mask;
  }

  #[Route('/superadmin/hello',
    methods: ['GET']
  )]
  public function helloAction(Request $request) {

    #Establish the list of admin users
    $userList = $this->fetchAll("
      SELECT id, username, roles
        FROM USERS
       ORDER BY username ASC");

    #Establish the list of kennels
    $kennelList = $this->fetchAll("
      SELECT KENNEL_NAME, KENNEL_DESCRIPTION, KENNEL_ABBREVIATION, IN_RECORD_KEEPING, SITE_ADDRESS, KENNEL_KY,
             EXISTS(SELECT 1 FROM HASHES WHERE HASHES.KENNEL_KY = KENNELS.KENNEL_KY) AS IN_USE
        FROM KENNELS
       ORDER BY IN_RECORD_KEEPING DESC, SITE_ADDRESS DESC");

    $hareTypes = $this->fetchAll("
      SELECT *, EXISTS(SELECT 1
                         FROM HARINGS
                        WHERE HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE) AS IN_USE
        FROM HARE_TYPES ORDER BY SEQ");

    $hashTypes = $this->fetchAll("
      SELECT *, EXISTS(SELECT 1
                         FROM HASHES_TABLE
                        WHERE HASHES_TABLE.HASH_TYPE & HASH_TYPES.HASH_TYPE = HASH_TYPES.HASH_TYPE) AS IN_USE
        FROM HASH_TYPES
       ORDER BY SEQ");

    $siteConfig = $this->fetchAll("
      SELECT NAME, VALUE
        FROM SITE_CONFIG
       WHERE DESCRIPTION IS NOT NULL
       ORDER BY NAME");

    $ridiculous = $this->fetchAll("
      SELECT NAME, VALUE
        FROM SITE_CONFIG
       WHERE NAME LIKE 'ridiculous%'
       ORDER BY NAME");

    return $this->render('superadmin_landing.twig', [
      'pageTitle' => 'This is the super admin landing screen',
      'subTitle1' => 'This is the super admin landing screen',
      'user_list' => $userList,
      'kennel_list' => $kennelList,
      'hare_types' => $hareTypes,
      'hash_types' => $hashTypes,
      'site_config' => $siteConfig,
      'ridiculous' => $ridiculous,
      'csrf_token' => $this->getCsrfToken('superadmin') ]);
  }

  #Define the action
  public function logonScreenAction(Request $request){

    # Establisht the last error
    $lastError = $this->container->get('security.last_error')($request);
    #$this->container->get('monolog')->addDebug($lastError);

    # Establish the last username
    $lastUserName = $this->container->get('session')->get('_security.last_username');
    #$lastUserName = $this->container->get('session')->get('_security.last_username');
    #$this->container->get('monolog')->addDebug($lastUserName);

    # Establish the return value
    $returnValue =  $this->render('superadmin_logon_screen.twig', array (
      'pageTitle' => 'Super Admin Logon',
      'pageHeader' => 'Please log on!',
      'error' => $lastError,
      'last_username' => $lastUserName,
    ));

    # Return the return value;
    return $returnValue;
  }

  public function logoutAction(Request $request){

    # Invalidate the session
    $this->container->get('session')->invalidate();

    # Redirect the user to the root url
    return new RedirectResponse('/');
  }

  #[Route('/superadmin/{kennel_abbreviation}/editkennel/ajaxform',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function modifyKennelAjaxPreAction(string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT *
        FROM KENNELS
       WHERE KENNEL_ABBREVIATION = ?";

    # Make a database call to obtain the information
    $kennelValue = $this->fetchAssoc($sql, [ $kennel_abbreviation ]);

    $sql = "
      SELECT GROUP_CONCAT(AWARD_LEVEL ORDER BY AWARD_LEVEL)
        FROM AWARD_LEVELS
       GROUP BY KENNEL_KY
      HAVING KENNEL_KY = (SELECT KENNEL_KY
                            FROM KENNELS
                           WHERE KENNEL_ABBREVIATION = ?)";

    $awardLevels = $this->fetchOne($sql, [ $kennel_abbreviation ]);

    $hareTypes = $this->fetchAll("
      SELECT *, (COALESCE((SELECT true
                             FROM KENNELS
                            WHERE KENNEL_ABBREVIATION = ?
                              AND KENNELS.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE), false)) AS SELECTED
        FROM HARE_TYPES
       ORDER BY SEQ", [ $kennel_abbreviation ]);

    $hashTypes = $this->fetchAll("
      SELECT *, (COALESCE((SELECT true
                             FROM KENNELS
                            WHERE KENNEL_ABBREVIATION = ?
                              AND KENNELS.HASH_TYPE_MASK & HASH_TYPES.HASH_TYPE = HASH_TYPES.HASH_TYPE), false)) AS SELECTED
        FROM HASH_TYPES
       ORDER BY SEQ", [ $kennel_abbreviation ]);

    return $this->render('edit_kennel_form_ajax.twig', [
      'pageTitle' => 'Modify a Kennel!',
      'kennel_abbreviation' => $kennel_abbreviation,
      'kennelValue' => $kennelValue,
      'awardLevels' => $awardLevels,
      'hare_types' => $hareTypes,
      'hash_types' => $hashTypes,
      'showAwardsPage' => $this->showAwardsPage(),
      'csrf_token' => $this->getCsrfToken('kennel'.$kennel_abbreviation) ]);
  }

  #[Route('/superadmin/{kennel_abbreviation}/editkennel/ajaxform',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function modifyKennelAjaxPostAction(Request $request, string $kennel_abbreviation) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('kennel'.$kennel_abbreviation, $token);

    $theKennelName = trim(strip_tags($_POST['kennelName']));
    $theKennelAbbreviation = trim(strip_tags($_POST['kennelAbbreviation']));
    $theKennelDescription = trim(strip_tags($_POST['kennelDescription']));
    $theSiteAddress = trim(strip_tags($_POST['siteAddress']));
    $theInRecordKeeping = array_key_exists('inRecordKeeping', $_POST) ?
      (int) trim(strip_tags($_POST['inRecordKeeping'])) : 0;
    $theAwardLevels = str_replace(' ', '', trim(strip_tags($_POST['awardLevels'])));
    $theOrigAwardLevels = trim(strip_tags($_POST['origAwardLevels']));
    $theHashTypes = $_POST['hashTypes'];
    $theHareTypes = $_POST['hareTypes'];

    if($theSiteAddress == "") {
      $theSiteAddress = null;
    }

    $theHashTypeMask = $this->convertInputToMask($theHashTypes);
    $theHareTypeMask = $this->convertInputToMask($theHareTypes);

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($theInRecordKeeping !=0 && $theInRecordKeeping != 1) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on inRecordKeeping";
    }

    if($passedValidation) {

      $sql = "
        UPDATE KENNELS
           SET KENNEL_NAME = ?, KENNEL_ABBREVIATION = ?, KENNEL_DESCRIPTION = ?,
               SITE_ADDRESS = ?, IN_RECORD_KEEPING = ?, HASH_TYPE_MASK = ?,
               HARE_TYPE_MASK = ?
         WHERE KENNEL_ABBREVIATION = ?";

        $this->getWriteConnection()->executeUpdate($sql, [
          $theKennelName, $theKennelAbbreviation, $theKennelDescription,
          $theSiteAddress, $theInRecordKeeping, $theHashTypeMask,
          $theHareTypeMask, $kennel_abbreviation ]);

      if($theAwardLevels != $theOrigAwardLevels) {
        $sql = "
          DELETE
            FROM AWARD_LEVELS
           WHERE KENNEL_KY = (SELECT KENNEL_KY
                                FROM KENNELS
                               WHERE KENNEL_ABBREVIATION = ?)";

        $this->dbw->executeUpdate($sql, [ $kennel_abbreviation ]);

        $sql = "
          INSERT INTO AWARD_LEVELS(KENNEL_KY, AWARD_LEVEL)
          VALUES((SELECT KENNEL_KY FROM KENNELS WHERE KENNEL_ABBREVIATION = ?), ?)";

        $kennelAwards = preg_split("/,/", $theAwardLevels);

        foreach($kennelAwards as $kennelAward) {
          $this->getWriteConnection()->executeUpdate($sql, [ $kennel_abbreviation, (int) $kennelAward ]);
        }
      }

      #Audit this activity
      $actionType = "Kennel Modification (Ajax)";
      $actionDescription = "Modified kennel $kennel_abbreviation";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/newkennel/ajaxform',
    methods: ['GET']
  )]
  public function newKennelAjaxPreAction() {

    $kennelValue['KENNEL_NAME'] = "";
    $kennelValue['KENNEL_ABBREVIATION'] = "";
    $kennelValue['KENNEL_DESCRIPTION'] = "";
    $kennelValue['SITE_ADDRESS'] = "";
    $kennelValue['IN_RECORD_KEEPING'] = 1;

    $awardLevels = "10,25,50,69,100,200,300,400,500,600,700,800,900,1000";

    $hareTypes = $this->fetchAll("
      SELECT *, false AS SELECTED
        FROM HARE_TYPES
       ORDER BY SEQ", []);

    $hashTypes = $this->fetchAll("
      SELECT *, false AS SELECTED
        FROM HASH_TYPES
       ORDER BY SEQ", []);

    return $this->render('edit_kennel_form_ajax.twig', [
      'pageTitle' => 'Add a Kennel!',
      'kennel_abbreviation' => '_none',
      'kennelValue' => $kennelValue,
      'awardLevels' => $awardLevels,
      'hare_types' => $hareTypes,
      'hash_types' => $hashTypes,
      'showAwardsPage' => $this->showAwardsPage(),
      'csrf_token' => $this->getCsrfToken('kennel') ]);
  }

  #[Route('/superadmin/newkennel/ajaxform',
    methods: ['POST']
  )]
  public function newKennelAjaxPostAction(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('kennel', $token);

    $theKennelName = trim(strip_tags($_POST['kennelName']));
    $theKennelAbbreviation = trim(strip_tags($_POST['kennelAbbreviation']));
    $theKennelDescription = trim(strip_tags($_POST['kennelDescription']));
    $theSiteAddress = trim(strip_tags($_POST['siteAddress']));
    $theInRecordKeeping = array_key_exists('inRecordKeeping', $_POST) ?
        (int) trim(strip_tags($_POST['inRecordKeeping'])) : 0;
    $theAwardLevels = str_replace(' ', '', trim(strip_tags($_POST['awardLevels'])));
    $theHashTypes = $_POST['hashTypes'];
    $theHareTypes = $_POST['hareTypes'];

    if($theSiteAddress == "") {
      $theSiteAddress = null;
    }

    $theHashTypeMask = $this->convertInputToMask($theHashTypes);
    $theHareTypeMask = $this->convertInputToMask($theHareTypes);

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($theInRecordKeeping !=0 && $theInRecordKeeping != 1) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on inRecordKeeping";
    }

    if($passedValidation) {

      $sql = "
        INSERT INTO KENNELS(KENNEL_NAME, KENNEL_ABBREVIATION, KENNEL_DESCRIPTION,
                            SITE_ADDRESS, IN_RECORD_KEEPING, HASH_TYPE_MASK, HARE_TYPE_MASK)
        VALUES(?, ?, ?, ?, ?, ?, ?)";

      $this->getWriteConnection()->executeUpdate($sql, [
        $theKennelName, $theKennelAbbreviation, $theKennelDescription,
        $theSiteAddress, $theInRecordKeeping, $theHashTypeMask,
        $theHareTypeMask ]);

      $sql = "
        INSERT INTO AWARD_LEVELS(KENNEL_KY, AWARD_LEVEL)
          VALUES((SELECT KENNEL_KY FROM KENNELS WHERE KENNEL_ABBREVIATION = ?), ?)";

      $kennelAwards = preg_split("/,/", $theAwardLevels);

      foreach($kennelAwards as $kennelAward) {
        $this->getWriteConnection()->executeUpdate($sql, [ $theKennelAbbreviation, (int) $kennelAward ]);
      }

      #Audit this activity
      $actionType = "Kennel Modification (Ajax)";
      $actionDescription = "Modified kennel $theKennelAbbreviation";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/{hare_type}/editharetype/ajaxform',
    methods: ['GET'],
    requirements: [
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function modifyHareTypeAjaxPreAction(int $hare_type) {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT *
        FROM HARE_TYPES
       WHERE HARE_TYPE = ?";

    # Make a database call to obtain the hasher information
    $hareTypeValue = $this->fetchAssoc($sql, [ $hare_type ]);

    return $this->render('edit_hare_type_form_ajax.twig', [
      'pageTitle' => 'Modify a Hare Type!',
      'hareTypeValue' => $hareTypeValue,
      'hare_type' => $hare_type,
      'csrf_token' => $this->getCsrfToken('mod_hare_type'.$hare_type) ]);
  }

  #[Route('/superadmin/{hare_type}/editharetype/ajaxform',
    methods: ['POST'],
    requirements: [
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function modifyHareTypeAjaxPostAction(Request $request, int $hare_type) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('mod_hare_type'.$hare_type, $token);

    $theHareTypeName = trim(strip_tags($_POST['hareTypeName']));
    $theSequence = (int) trim(strip_tags($_POST['sequence']));
    $theChartColor = trim(strip_tags($_POST['chartColor']));

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($passedValidation) {

      $sql = "
        UPDATE HARE_TYPES
           SET HARE_TYPE_NAME = ?, SEQ = ?, CHART_COLOR = ?
         WHERE HARE_TYPE = ?";

        $this->getWriteConnection()->executeUpdate($sql, [ $theHareTypeName,
          $theSequence, $theChartColor, $hare_type ]);

      #Audit this activity
      $actionType = "Hare Type Modification (Ajax)";
      $actionDescription = "Modified hare type $theHareTypeName";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/newharetype/ajaxform',
    methods: ['GET']
  )]
  public function newHareTypeAjaxPreAction() {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT MAX(SEQ) + 10 AS SEQ, null AS HARE_TYPE_NAME, '255,0,0' AS CHART_COLOR
        FROM HARE_TYPES";

    # Make a database call to obtain the hasher information
    $hareTypeValue = $this->fetchAssoc($sql, []);

    return $this->render('edit_hare_type_form_ajax.twig', [
      'pageTitle' => 'Create a Hare Type!',
      'hareTypeValue' => $hareTypeValue,
      'hare_type' => -1,
      'csrf_token' => $this->getCsrfToken('hare_type') ]);
  }

  #[Route('/superadmin/newharetype/ajaxform',
    methods: ['POST']
  )]
  public function newHareTypeAjaxPostAction(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('hare_type', $token);

    $theHareTypeName = trim(strip_tags($_POST['hareTypeName']));
    $theSequence = (int) trim(strip_tags($_POST['sequence']));
    $theChartColor = trim(strip_tags($_POST['chartColor']));

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($passedValidation) {

      $hare_type = 1;
      $sql = "SELECT HARE_TYPE FROM HARE_TYPES WHERE HARE_TYPE = ?";
      while(true) {
        if(!$this->fetchOne($sql, [ $hare_type ])) break;
        $hare_type *= 2;
      }

      $sql = "
        INSERT INTO HARE_TYPES(HARE_TYPE_NAME, SEQ, CHART_COLOR, HARE_TYPE)
        VALUES(?, ?, ?, ?)";

        $this->getWriteConnection()->executeUpdate($sql, [ $theHareTypeName,
          $theSequence, $theChartColor, $hare_type ]);

      #Audit this activity
      $actionType = "Hare Type Creation (Ajax)";
      $actionDescription = "Created hare type $theHareTypeName";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/{hash_type}/edithashtype/ajaxform',
    methods: ['GET'],
    requirements: [
      'hash_type' => '%app.pattern.hash_type%']
  )]
  public function modifyHashTypeAjaxPreAction(int $hash_type) {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT *
        FROM HASH_TYPES
       WHERE HASH_TYPE = ?";

    # Make a database call to obtain the hasher information
    $hashTypeValue = $this->fetchAssoc($sql, [ $hash_type ]);

    $hareTypes = $this->fetchAll("
      SELECT *, (COALESCE((SELECT true
                             FROM HASH_TYPES
                            WHERE HASH_TYPE = ?
                              AND HASH_TYPES.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE), false)) AS SELECTED
        FROM HARE_TYPES
       ORDER BY SEQ",  [ $hash_type ]);

    return $this->render('edit_hash_type_form_ajax.twig', [
      'pageTitle' => 'Modify a Hash Type!',
      'hashTypeValue' => $hashTypeValue,
      'hash_type' => $hash_type,
      'hare_types' => $hareTypes,
      'csrf_token' => $this->getCsrfToken('mod_hash_type'.$hash_type) ]);
  }

  #[Route('/superadmin/{hash_type}/edithashtype/ajaxform',
    methods: ['POST'],
    requirements: [
      'hash_type' => '%app.pattern.hash_type%']
  )]
  public function modifyHashTypeAjaxPostAction(Request $request, int $hash_type) {
    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('mod_hash_type'.$hash_type, $token);

    $theHashTypeName = trim(strip_tags($_POST['hashTypeName']));
    $theSequence = (int) trim(strip_tags($_POST['sequence']));
    $theHareTypes = $_POST['hareTypes'];

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    $theHareTypeMask = $this->convertInputToMask($theHareTypes);

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($theHareTypeMask <= 0) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on hare types";
    }

    if($passedValidation) {

      $sql = "
        UPDATE HASH_TYPES
           SET HASH_TYPE_NAME = ?, SEQ = ?, HARE_TYPE_MASK = ?
         WHERE HASH_TYPE = ?";

        $this->getWriteConnection()->executeUpdate($sql, [ $theHashTypeName, $theSequence,
          $theHareTypeMask, $hash_type ]);

      #Audit this activity
      $actionType = "Hash Type Modification (Ajax)";
      $actionDescription = "Modified hash type $theHashTypeName";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/newhashtype/ajaxform',
    methods: ['GET']
  )]
  public function newHashTypeAjaxPreAction() {

    $sql = "
      SELECT MAX(SEQ)+10 AS SEQ, NULL AS HASH_TYPE_NAME
        FROM HASH_TYPES";

    # Make a database call to obtain the hasher information
    $hashTypeValue = $this->fetchAssoc($sql, []);

    $hareTypes = $this->fetchAll("
      SELECT *, false AS SELECTED
        FROM HARE_TYPES
       ORDER BY SEQ", []);

    return $this->render('edit_hash_type_form_ajax.twig', [
      'pageTitle' => 'Create a Hash Type!',
      'hashTypeValue' => $hashTypeValue,
      'hash_type' => -1,
      'hare_types' => $hareTypes,
      'csrf_token' => $this->getCsrfToken('hash_type') ]);
  }

  #[Route('/superadmin/newhashtype/ajaxform',
    methods: ['POST']
  )]
  public function newHashTypeAjaxPostAction(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('hash_type', $token);

    $theHashTypeName = trim(strip_tags($_POST['hashTypeName']));
    $theSequence = (int) trim(strip_tags($_POST['sequence']));
    $theHareTypes = $_POST['hareTypes'];

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    $theHareTypeMask = $this->convertInputToMask($theHareTypes);

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($theHareTypeMask <= 0) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on hare types";
    }

    if($passedValidation) {

      $hash_type = 1;
      $sql = "SELECT HASH_TYPE FROM HASH_TYPES WHERE HASH_TYPE = ?";
      while(true) {
        if(!$this->fetchOne($sql, [ $hash_type ])) break;
        $hash_type *= 2;
      }

      $sql = "
        INSERT INTO HASH_TYPES(HASH_TYPE, HASH_TYPE_NAME, SEQ, HARE_TYPE_MASK)
        VALUES(?, ?, ?, ?)";

        $this->getWriteConnection()->executeUpdate($sql, [
          $hash_type, $theHashTypeName, $theSequence, $theHareTypeMask ]);

      #Audit this activity
      $actionType = "Hash Type Creation (Ajax)";
      $actionDescription = "Created hash type $theHashTypeName";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/{user_id}/edituser/ajaxform',
    methods: ['GET'],
    requirements: [
      'user_id' => '%app.pattern.user_id%']
  )]
  public function modifyUserAjaxPreAction(int $user_id) {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT username, (INSTR(roles, 'ROLE_SUPERADMIN') > 1) AS SUPERADMIN
        FROM USERS
       WHERE ID = ?";

    # Make a database call to obtain the hasher information
    $userValue = $this->fetchAssoc($sql, [ $user_id ]);

    return $this->render('edit_user_form_ajax.twig', [
      'pageTitle' => 'Modify a User!',
      'userValue' => $userValue,
      'user_id' => $user_id,
      'csrf_token' => $this->getCsrfToken('mod_user'.$user_id) ]);
  }

  #[Route('/superadmin/{user_id}/edituser/ajaxform',
    methods: ['POST'],
    requirements: [
      'user_id' => '%app.pattern.user_id%']
  )]
  public function modifyUserAjaxPostAction(Request $request, int $user_id,
      UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('mod_user'.$user_id, $token);

    $theUsername = trim(strip_tags($_POST['username']));
    $thePassword = trim(strip_tags($_POST['password']));
    $theSuperadmin = array_key_exists('superadmin', $_POST) ? $_POST['superadmin'] : null;

    // Establish a "passed validation" variable
    $passedValidation = FALSE;

    if($theSuperadmin == "1") {
      $roles="ROLE_ADMIN,ROLE_SUPERADMIN";
    } else {
      $roles="ROLE_ADMIN";
    }

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    $user = $userRepository->findOneBy([ 'id' => $user_id ]);

    if(strlen($thePassword) >= 8) {
      // compute the encoded password for the new password
      $encodedNewPassword = $passwordHasher->hashPassword($user, $thePassword);
      $user->setPassword($encodedNewPassword);
      $passedValidation = TRUE;
    } else if(strlen($thePassword) != 0) {
      $returnMessage .= " |Failed validation on password";
    } else {
      $passedValidation = TRUE;
    }

    $user->setUsername($theUsername);
    $user->setRoles($roles);

    if($passedValidation) {

      $userRepository->save($user, true);

      #Audit this activity
      $actionType = "User Modification (Ajax)";
      $actionDescription = "Modified user $theUsername";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/{name}/editsiteconfig/ajaxform',
    methods: ['GET'],
    requirements: [
      'name' => '%app.pattern.name%']
  )]
  public function modifySiteConfigAjaxPreAction(string $name) {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT *
        FROM SITE_CONFIG
       WHERE NAME = ?";

    # Make a database call to obtain the hasher information
    $item = $this->fetchAssoc($sql, [ $name ]);

    return $this->render('edit_site_config_form_ajax.twig', [
      'pageTitle' => 'Modify a Configuration Variable: '.$name,
      'item' => $item,
      'csrf_token' => $this->getCsrfToken('mod_'.$name) ]);
  }

  #[Route('/superadmin/{name}/editsiteconfig/ajaxform',
    methods: ['POST'],
    requirements: [
      'name' => '%app.pattern.name%']
  )]
  public function modifySiteConfigAjaxPostAction(Request $request, string $name) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('mod_'.$name, $token);

    $theValue = trim($_POST['value']);

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($passedValidation) {

      $sql = "
        UPDATE SITE_CONFIG
           SET VALUE = ?
         WHERE NAME = ?
           AND DESCRIPTION IS NOT NULL";

      $this->getWriteConnection()->executeUpdate($sql, [ $theValue, $name ]);

      #Audit this activity
      $actionType = "SITE CONFIG Modification (Ajax)";
      $actionDescription = "Modified site config $name";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/{ridiculous}/editridiculous/ajaxform',
    methods: ['GET'],
    requirements: [
      'ridiculous' => '%app.pattern.ridiculous%']
  )]
  public function modifyRidiculousAjaxPreAction(string $ridiculous) {

    # Declare the SQL used to retrieve this information
    $sql = "SELECT NAME, VALUE FROM SITE_CONFIG WHERE NAME = ?";

    # Make a database call to obtain the hasher information
    $item = $this->fetchAssoc($sql, [ $ridiculous ]);

    return $this->render('edit_ridiculous_form_ajax.twig', [
      'pageTitle' => 'Edit Ridiculous Stat',
      'item' => $item,
      'csrf_token' => $this->getCsrfToken('mod_'.$item['NAME']) ]);
  }

  #[Route('/superadmin/{ridiculous}/editridiculous/ajaxform',
    methods: ['POST'],
    requirements: [
      'ridiculous' => '%app.pattern.ridiculous%']
  )]
  public function modifyRidiculousAjaxPostAction(Request $request, string $ridiculous) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('mod_'.$ridiculous, $token);

    $theValue = trim($_POST['value']);

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if(substr($ridiculous, 0, strlen("ridiculous")) != "ridiculous") {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on key name";
    }

    if($passedValidation) {

      $sql = "
        UPDATE SITE_CONFIG
           SET VALUE = ?
         WHERE NAME = ?
           AND DESCRIPTION IS NULL";

      $this->getWriteConnection()->executeUpdate($sql, [ $theValue,
        $ridiculous ]);

      #Audit this activity
      $actionType = "SITE CONFIG Modification (Ajax)";
      $actionDescription = "Modified site config $ridiculous";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/newridiculous/ajaxform',
    methods: ['GET']
  )]
  public function newRidiculousAjaxPreAction() {

    $item['NAME']='new';
    $item['VALUE']="";

    return $this->render('edit_ridiculous_form_ajax.twig', [
      'pageTitle' => 'Create New Ridiculous Stat',
      'item' => $item,
      'csrf_token' => $this->getCsrfToken('ridic') ]);
  }

  #[Route('/superadmin/newridiculous/ajaxform',
    methods: ['POST']
  )]
  public function newRidiculousAjaxPostAction(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('ridic', $token);

    $theValue = trim($_POST['value']);

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if($passedValidation) {

      $sql = "INSERT INTO SITE_CONFIG(NAME, VALUE) VALUES(?, ?)";

      for($i=0; $i<999; $i++) {
        try {
          $name = "ridiculous".$i;
          $this->getWriteConnection()->executeUpdate($sql, [ $name, $theValue ]);
        } catch(\Exception $e) {
          continue;
        }
        break;
      }

      #Audit this activity
      $actionType = "SITE CONFIG Modification (Ajax)";
      $actionDescription = "New site config $name";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/newuser/ajaxform',
    methods: ['GET']
  )]
  public function newUserAjaxPreAction() {

    $userValue['username']='';
    $userValue['SUPERADMIN']=false;

    return $this->render('edit_user_form_ajax.twig', [
      'pageTitle' => 'Add a User!',
      'userValue' => $userValue,
      'user_id' => -1,
      'csrf_token' => $this->getCsrfToken('newUser') ]);
  }

  #[Route('/superadmin/newuser/ajaxform',
    methods: ['POST']
  )]
  public function newUserAjaxPostAction(Request $request, UserPasswordHasherInterface $passwordHasher,
      UserRepository $userRepository) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('newUser', $token);

    $theUsername = trim(strip_tags($_POST['username']));
    $thePassword = trim(strip_tags($_POST['password']));
    $theSuperadmin = array_key_exists('superadmin', $_POST) ? $_POST['superadmin'] : null;

    // Establish a "passed validation" variable
    $passedValidation = FALSE;

    if($theSuperadmin == "1") {
      $roles="ROLE_ADMIN,ROLE_SUPERADMIN";
    } else {
      $roles="ROLE_ADMIN";
    }

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if(strlen($thePassword) >= 8) {

      // compute the encoded password for the new password
      $user = new User();
      $user->setUsername($theUsername);
      $user->setRoles($roles);
      
      $encodedNewPassword = $passwordHasher->hashPassword($user, $thePassword);
      $user->setPassword($encodedNewPassword);

      $passedValidation = TRUE;
    } else {
      $returnMessage .= " |Failed validation on password";
    }

    if($passedValidation) {

      $userRepository->save($user, true);

      #Audit this activity
      $actionType = "User Creation (Ajax)";
      $actionDescription = "Created user $theUsername";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/superadmin/deleteridiculous',
    methods: ['POST']
  )]
  public function deleteRidiculous(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('superadmin', $token);

    $ridiculous = $_POST['id'];
    if(substr($ridiculous, 0, strlen("ridiculous")) == "ridiculous") {

      $sql = "DELETE FROM SITE_CONFIG WHERE NAME = ?";
      $this->getWriteConnection()->executeUpdate($sql, [ $ridiculous ]);

      $actionType = "Site Config Deletion (Ajax)";
      $actionDescription = "Deleted site config key $ridiculous";

      $this->auditTheThings($request, $actionType, $actionDescription);
    }

    return new JsonResponse("");
  }

  #[Route('/superadmin/deleteuser',
    methods: ['POST']
  )]
  public function deleteUser(Request $request, UserRepository $userRepository) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('superadmin', $token);

    $user_id = $_POST['id'];

    $user = $userRepository->findOneBy([ 'id' => $user_id ]);

    $currentUsername = $this->getUsername();

    if(($currentUsername !== 'UNKNOWN') && ($user->getUsername() != $currentUsername)) {

      $userRepository->remove($user, true);

      $actionType = "User Deletion (Ajax)";
      $actionDescription = "Deleted user ".$user->getUsername();

      $this->auditTheThings($request, $actionType, $actionDescription);
    }

    return new JsonResponse("");
  }

  #[Route('/superadmin/deletekennel',
    methods: ['POST']
  )]
  public function deleteKennel(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('superadmin', $token);

    $kennel_ky = (int) $_POST['id'];

    $sql = "SELECT KENNEL_ABBREVIATION FROM KENNELS WHERE KENNEL_KY = ?";
    $kennel = $this->fetchOne($sql, [ $kennel_ky ]);

    $sql = "DELETE FROM KENNELS WHERE KENNEL_KY = ?";
    $this->getWriteConnection()->executeUpdate($sql, [ $kennel_ky ]);

    $actionType = "Kennel Deletion (Ajax)";
    $actionDescription = "Deleted kennel $kennel";

    $this->auditTheThings($request, $actionType, $actionDescription);

    return new JsonResponse("");
  }

  #[Route('/superadmin/deletehashtype',
    methods: ['POST']
  )]
  public function deleteHashType(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('superadmin', $token);

    $hash_type = $_POST['id'];

    $sql = "
      SELECT EXISTS(SELECT 1
                      FROM HASHES_TABLE
                     WHERE HASHES_TABLE.HASH_TYPE & ? = HASHES_TABLE.HASH_TYPE) AS IN_USE";
    $in_use = $this->fetchOne($sql, [ $hash_type ]);

    if(!$in_use) {
      $sql = "SELECT HASH_TYPE_NAME FROM HASH_TYPES WHERE HASH_TYPE = ?";
      $hash_type_name = $this->fetchOne($sql, [ $hash_type ]);

      $sql = "DELETE FROM HASH_TYPES WHERE HASH_TYPE = ?";
      $this->getWriteConnection()->executeUpdate($sql, [ $hash_type ]);

      $actionType = "Hash Type Deletion (Ajax)";
      $actionDescription = "Deleted hash type $hash_type_name";

      $this->auditTheThings($request, $actionType, $actionDescription);
    }

    return new JsonResponse("");
  }

  #[Route('/superadmin/deleteharetype',
    methods: ['POST']
  )]
  public function deleteHareType(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('superadmin', $token);

    $hare_type = $_POST['id'];

    $sql = "
      SELECT EXISTS(SELECT 1
                      FROM HARINGS
                     WHERE HARINGS.HARE_TYPE & ? = HARINGS.HARE_TYPE) AS IN_USE";
    $in_use = $this->fetchOne($sql, [ $hare_type ]);

    if(!$in_use) {
      $sql = "SELECT HARE_TYPE_NAME FROM HARE_TYPES WHERE HARE_TYPE = ?";
      $hare_type_name = $this->fetchOne($sql, [ $hare_type ]);

      $sql = "DELETE FROM HARE_TYPES WHERE HARE_TYPE = ?";
      $this->getWriteConnection()->executeUpdate($sql, [ $hare_type ]);

      $actionType = "Hare Type Deletion (Ajax)";
      $actionDescription = "Deleted hare type $hare_type_name";

      $this->auditTheThings($request, $actionType, $actionDescription);
    }

    return new JsonResponse("");
  }

  #[Route('/superadmin/integrity',
    methods: ['GET']
  )]
  public function integrityChecks() {

    $sql = "
      SELECT KENNEL_NAME, KENNEL_KY
        FROM KENNELS
       WHERE IN_RECORD_KEEPING = 1
       ORDER BY KENNEL_NAME";

    $reports = $this->fetchAll($sql, []);

    foreach($reports as &$report) {
      $messages = [];

      $sql = "
        SELECT EVENT_DATE
          FROM HASHES_TABLE
         WHERE KENNEL_KY = ?
         GROUP BY EVENT_DATE
        HAVING COUNT(*) > 1
         ORDER BY EVENT_DATE";

      $dup_items = $this->fetchAll($sql, [ $report['KENNEL_KY'] ]);

      foreach($dup_items as &$dup_item) {

        $sql = "
          SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION AS EVENT_NAME
            FROM HASHES_TABLE
           WHERE KENNEL_KY = ?
             AND EVENT_DATE = ?
           ORDER BY KENNEL_EVENT_NUMBER";

        $results = $this->fetchAll($sql, [ $report['KENNEL_KY'], $dup_item['EVENT_DATE'] ]);

        foreach($results as $result) {
          array_push($messages, 'Event number '.$result['KENNEL_EVENT_NUMBER'].' ('.$result['EVENT_NAME'].') has duplicate event date: '.$dup_item['EVENT_DATE'].'.');
        }
      }

      $sql = "
        SELECT KENNEL_EVENT_NUMBER
          FROM HASHES_TABLE
         WHERE KENNEL_KY = ?
         GROUP BY KENNEL_EVENT_NUMBER
        HAVING COUNT(*) > 1
         ORDER BY KENNEL_EVENT_NUMBER";

      $dup_items = $this->fetchAll($sql, [ $report['KENNEL_KY'] ]);

      foreach($dup_items as &$dup_item) {

        $sql = "
          SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION AS EVENT_NAME
            FROM HASHES_TABLE
           WHERE KENNEL_KY = ?
             AND KENNEL_EVENT_NUMBER = ?
           ORDER BY KENNEL_EVENT_NUMBER";

        $results = $this->fetchAll($sql, [ $report['KENNEL_KY'], $dup_item['KENNEL_EVENT_NUMBER'] ]);

        foreach($results as $result) {
          array_push($messages, 'Event number '.$result['KENNEL_EVENT_NUMBER'].' ('.$result['EVENT_NAME'].') has duplicate event number: '.$dup_item['KENNEL_EVENT_NUMBER'].'.');
        }

      }

      $sql = "
        SELECT SPECIAL_EVENT_DESCRIPTION
          FROM HASHES_TABLE
         WHERE KENNEL_KY = ?
         GROUP BY SPECIAL_EVENT_DESCRIPTION
        HAVING COUNT(*) > 1
         ORDER BY SPECIAL_EVENT_DESCRIPTION";

      $dup_items = $this->fetchAll($sql, [ $report['KENNEL_KY'] ]);

      foreach($dup_items as &$dup_item) {

        $sql = "
          SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION AS EVENT_NAME
            FROM HASHES_TABLE
           WHERE KENNEL_KY = ?
             AND SPECIAL_EVENT_DESCRIPTION = ?
           ORDER BY KENNEL_EVENT_NUMBER";

        $results = $this->fetchAll($sql, [ $report['KENNEL_KY'], $dup_item['SPECIAL_EVENT_DESCRIPTION'] ]);

        foreach($results as $result) {
          array_push($messages, 'Event number '.$result['KENNEL_EVENT_NUMBER'].' ('.$result['EVENT_NAME'].') has duplicate event name: '.$dup_item['SPECIAL_EVENT_DESCRIPTION'].'.');
        }
      }

      $report['MESSAGES'] = $messages;
    }

    foreach($reports as &$report) {
      if(count($report['MESSAGES']) == 0) {
        array_push($report['MESSAGES'], 'No database issues were found.');
      }
    }

    return $this->render('superadmin_integrity_checks.twig', [
      'pageTitle' => 'Database Integrity Checks: Results',
      'reports' => $reports ]);
  }
}
