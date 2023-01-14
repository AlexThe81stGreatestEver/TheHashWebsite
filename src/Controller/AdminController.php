<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\PasswordChangeTask;
use App\Entity\User;
use App\Repository\UserRepository;
use App\SqlQueries;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Validator\ConstraintValidator;

class AdminController extends BaseController
{
  private SqlQueries $sqlQueries;

  public function __construct(ManagerRegistry $doctrine, SqlQueries $sqlQueries) {
    parent::__construct($doctrine);
    $this->sqlQueries = $sqlQueries;
  }

  protected function render(string $template, array $args = [], Response $response = null) : Response {
    $args['user'] = $this->getUser();
    return parent::render($template, $args, $response);
  }

  public function logoutAction(Request $request){

    # Invalidate the session
    $this->container->get('session')->invalidate();

    # Redirect the user to the root url
    return new RedirectResponse('/');
  }

  #[Route('/admin/hello',
    methods: ['GET']
  )]
  public function helloAction(Request $request){
    return $this->render('admin_landing.twig', [
      'pageTitle' => 'This is the admin landing screen',
      'subTitle1' => 'This is the admin landing screen',
      'showAwardsPage' => $this->showAwardsPage(),
      'hasLegacyHashCounts' => $this->hasLegacyHashCounts() ]);
  }

    public function listOrphanedHashersAction(Request $request){

      #Define the SQL to execute
      $sql = "SELECT *
              FROM
              	HASHERS
              WHERE
              	HASHERS.HASHER_KY NOT IN (SELECT HASHER_KY FROM HASHINGS)
                  AND
                  HASHERS.HASHER_KY NOT IN (SELECT HARINGS_HASHER_KY FROM HARINGS)";

      if($this->hasLegacyHashCounts()) {
        $sql .= " AND HASHERS.HASHER_KY NOT IN (SELECT HASHER_KY FROM LEGACY_HASHINGS)";
      }

      #Execute the SQL statement; create an array of rows
      $theList = $this->fetchAll($sql);

      # Establish and set the return value
      $returnValue = $this->render('admin_orphaned_hashers.twig',array(
        'pageTitle' => 'The List of Orphaned Hashers',
        'pageSubTitle' => 'Hashers who have never hashed or hared',
        'theList' => $theList,
        'tableCaption' => 'A list of all hashes ever, since forever.',
        'kennel_abbreviation' => 'XXX'
      ));

      #Return the return value
      return $returnValue;
    }

  #Define the action
  public function eventBudgetPreAction(Request $request, int $hash_id){

    #Obtain the hash event information

    #Obtain the default cost information
    $virginCost= 0;
    $houndCost = 8;
    $hareCost = 0;

    #Obtain the number of hounds
    $houndCountSQL = HOUND_COUNT_BY_HASH_KEY;
    $theHoundCountValue = $this->fetchAssoc($houndCountSQL, array((int) $hash_id));
    $theHoundCount = $theHoundCountValue['THE_COUNT'];

    #Obtain the number of hares
    $hareCountSQL = HARE_COUNT_BY_HASH_KEY;
    $theHareCountValue = $this->fetchAssoc($hareCountSQL, array((int) $hash_id));
    $theHareCount = $theHareCountValue['THE_COUNT'];

    # Establish and set the return value
    $returnValue = $this->render('event_budget.twig',array(
      'pageTitle' => 'Event Budget',
      'pageSubTitle' => 'Online Calculator',
      'pageCaption' => 'Event Budget Test Page Caption',
      'tableCaption' => 'Event Budget Test Table Caption',

      'defaultBeveragePrice' => 7.00,
      'defaultHareExpense' => 0,
      'defaultTreasuryDeposit' => 0,
      'defaultVirginCount' => 0,
      'defaultCashCollected' => 0,
      'defaultHareCost' => $hareCost,
      'defaultHoundCost' => $houndCost,
      'defaultVirginCost' => $virginCost,
      'defaultCharitableDonation' => 0,
      'defaultTipPercentage' => 20,
      'houndCount' => $theHoundCount ,
      'hareCount' => $theHareCount
    ));

    # Return the return value
    return $returnValue;
  }

  #[Route('/admin/newPassword/form',
    methods: ['GET', 'POST']
  )]
  public function newPasswordAction(Request $request, UserPasswordHasherInterface $passwordHasher,
      UserRepository $userRepository) {

    $task = new PasswordChangeTask();

    $form = $this->createFormBuilder($task)
      ->add('currentPassword', TextType::class)
      ->add('newPasswordInitial', TextType::class)
      ->add('newPasswordConfirmation', TextType::class)
      ->add('save', SubmitType::class, array('label' => 'Change your password!'))
      ->setAction('#')
      ->setMethod('POST')
      ->getForm();

    $form->handleRequest($request);

    #Establish the user value
    $user = $this->getUser();

    if($request->getMethod() == 'POST') {

      if ($form->isValid()) {
        #Obtain the name/value pairs from the form
        $task = $form->getData();

        #Establish the values from the form
        $tempCurrentPassword = $task->getCurrentPassword();
        $tempNewPasswordInitial = $task->getNewPasswordInitial();
        $tempNewPasswordConfirmation = $task->getNewPasswordConfirmation();

        // compute the encoded password for the new password
        $encodedNewPassword = $passwordHasher->hashPassword($user, $tempNewPasswordInitial);

        // verify current password
        $validCurrentPassword = $passwordHasher->isPasswordValid($user, $tempCurrentPassword);

        $foundValidationError=FALSE;

        if(!$validCurrentPassword) {
          $this->addFlash('danger', 'Wrong! You screwed up your current password.');
          $foundValidationError=TRUE;
        }

        #Check if the initial new password and the confirmation new password match
        $validNewPasswordsMatch = FALSE;
        if($tempNewPasswordInitial == $tempNewPasswordConfirmation) {
          $validNewPasswordsMatch = TRUE;
        } else {
          $this->addFlash('danger', 'Wrong! The new passwords do not match.');
          $foundValidationError=TRUE;
        }

        #Check if the new password matches password complexity requirements
        $validPasswordComplexity = FALSE;
        if (preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])(?=\S*[\W])\S*$', $tempNewPasswordInitial)) {
          $validPasswordComplexity = TRUE;
        } else {
          $this->addFlash('danger', 'Wrong! Your proposed password is too simple. It must be 8 characters long, contain a lower case letter, an upper case letter, a digit, and a special character!');
          $foundValidationError=TRUE;
        }

        if(!$foundValidationError) {

          #Update the password
          $user->setPassword($encodedNewPassword);
          $userRepository->save($user, true);

          #Audit this activity
          $actionType = "Password Change";
          $actionDescription = "Changed their password";
          $this->auditTheThings($request, $actionType, $actionDescription);

          #Show the confirmation message
          $this->addFlash('success', 'Success! You updated your password. Probably.');
        }

      } else{
        $this->addFlash('danger', 'Wrong! You screwed up.');
      }
    }

    return $this->render('admin_change_password_form.twig', [
      'pageTitle' => 'Password change',
      'pageHeader' => 'Your new password must contain letters, numbers, an odd number of prime numbers.',
      'form' => $form->createView(),
      'userid' => $user->getUsername() ]);
  }

  #[Route('/admin/viewAuditRecords',
    methods: ['GET']
  )]
  public function viewAuditRecordsPreActionJson() {
    return $this->render('audit_records_json.twig', [
      'pageTitle' => 'The audit records',
      'pageSubTitle' => 'Stuff that the admins have done' ]);
  }

  #[Route('/admin/viewAuditRecords',
    methods: ['POST']
  )]
  public function viewAuditRecordsJson() {

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------

    #Validate input start
    if(!is_numeric($inputStart)) {
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)) {
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1") {
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "1";
    $inputOrderDirectionExtracted = "asc";
    if(!is_null($inputOrderRaw)) {
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    } else {
      $inputOrderColumnIncremented =2;
      $inputOrderDirectionExtracted = "desc";
    }

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT USERNAME, AUDIT_TIME, ACTION_TYPE, ACTION_DESCRIPTION, IP_ADDR, AUDIT_KY,
             DATE_FORMAT(AUDIT_TIME,'%m/%d/%y %h:%i:%s %p') AS AUDIT_TIME_FORMATTED
        FROM AUDIT
       WHERE USERNAME LIKE ?
          OR AUDIT_TIME LIKE ?
          OR ACTION_TYPE LIKE ?
          OR ACTION_DESCRIPTION LIKE ?
          OR IP_ADDR LIKE ?
       ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM AUDIT
       WHERE USERNAME LIKE ?
          OR AUDIT_TIME LIKE ?
          OR ACTION_TYPE LIKE ?
          OR ACTION_DESCRIPTION LIKE ?
          OR IP_ADDR LIKE ?";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM AUDIT";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, [ $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified, $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, []))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/admin/deleteHash',
    methods: ['POST'],
  )]
  public function deleteHash(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('admin', $token);

    $hash_id = $_POST['id'];

    $sql = "
      SELECT KENNEL_EVENT_NUMBER, KENNEL_ABBREVIATION
        FROM HASHES_TABLE
        JOIN KENNELS
          ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
       WHERE HASH_KY = ?";
    $eventDetails = $this->fetchAssoc($sql, [ $hash_id ]);
    $kennel_event_number = $eventDetails['KENNEL_EVENT_NUMBER'];
    $kennel_abbreviation = $eventDetails['KENNEL_ABBREVIATION'];

    $sql = "DELETE FROM HASHES_TABLE WHERE HASH_KY = ?";
    $this->getWriteConnection()->executeUpdate($sql, [ $hash_id ]);

    $actionType = "Event Deletion (Ajax)";
    $actionDescription = "Deleted event ($kennel_abbreviation # $kennel_event_number)";
    $this->auditTheThings($request, $actionType, $actionDescription);

    return new JsonResponse("");
  }

  #[Route('/admin/listhashes2',
    methods: ['GET']
  )]
  #[Route('/admin/{kennel_abbreviation}/listhashes2',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function listHashesPreActionJson(Request $request, string $kennel_abbreviation = null) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = (int) $kennels[0]['KENNEL_KY'];
        $kennel_abbreviation = $kennels[0]['KENNEL_ABBREVIATION'];
      } else {
        return $this->render('admin_select_kennel.twig', [
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
	  'urlSuffix' => 'listhashes2' ]);
      }
    }

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES_TABLE WHERE KENNEL_KY = ?";

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy ]))['THE_COUNT'];

    #Define the sql that gets the overall counts
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES_TABLE WHERE PLACE_ID IS NULL AND KENNEL_KY = ?";

    #Perform the untiltered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $kennelKy ]))['THE_COUNT'];

    # Establish and set the return value
    return $this->render('admin_hash_list_json.twig', [
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => 'The List of *ALL* Hashes',
      'pageCaption' => "",
      'tableCaption' => "",
      'kennel_abbreviation' => $kennel_abbreviation,
      'totalHashes' => $theUnfilteredCount,
      'totalHashesToUpdate' => $theFilteredCount,
      'showBudgetPage' => $this->showBudgetPage(),
      'csrf_token' => $this->getCsrfToken('admin') ]);
  }

  #[Route('/admin/{kennel_abbreviation}/listhashes2',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHashListJson(Request $request, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------

    #Validate input start
    if(!is_numeric($inputStart)){
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "3";
    $inputOrderColumnIncremented = "3";
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)){
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT KENNEL_EVENT_NUMBER, HASH_KY, DATE_FORMAT(EVENT_DATE,\"%Y/%m/%d\") AS EVENT_DATE, EVENT_LOCATION, SPECIAL_EVENT_DESCRIPTION,
             PLACE_ID, COALESCE((SELECT 0 FROM HARINGS WHERE HARINGS.HARINGS_HASH_KY = HASHES_TABLE.HASH_KY LIMIT 1),
                           (SELECT 0 FROM HASHINGS WHERE HASHINGS.HASH_KY = HASHES_TABLE.HASH_KY LIMIT 1), 1) AS CAN_DELETE
        FROM HASHES_TABLE
       WHERE (KENNEL_EVENT_NUMBER LIKE ? OR EVENT_DATE LIKE ? OR EVENT_LOCATION LIKE ? OR SPECIAL_EVENT_DESCRIPTION LIKE ?)
         AND KENNEL_KY = ?
       ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHES_TABLE
       WHERE ( KENNEL_EVENT_NUMBER LIKE ? OR EVENT_DATE LIKE ? OR EVENT_LOCATION LIKE ? OR SPECIAL_EVENT_DESCRIPTION LIKE ?)
         AND KENNEL_KY = ?";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES_TABLE WHERE KENNEL_KY = ?";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, [ $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified, $kennelKy ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified, $inputSearchValueModified, $kennelKy ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/admin/listhashers2',
    methods: ['GET']
  )]
  public function listHashersPreActionJson(Request $request) {
    return $this->render('admin_hasher_list_json.twig', [
      'pageTitle' => 'The List of Hashers',
      'pageSubTitle' => '',
      'pageCaption' => "",
      'tableCaption' => ""
    ]);
  }

  #[Route('/admin/listhashers2',
    methods: ['POST']
  )]
  public function getHashersListJson(Request $request) {

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------

    #Validate input start
    if(!is_numeric($inputStart)) {
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)) {
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1") {
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "2";
    $inputOrderColumnIncremented = "2";
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)){
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT HASHER_NAME AS NAME, HASHER_KY AS THE_KEY, FIRST_NAME, LAST_NAME, HASHER_ABBREVIATION
        FROM HASHERS WHERE (HASHER_NAME LIKE ? OR FIRST_NAME LIKE ? OR LAST_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
       ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHERS
       WHERE (HASHER_NAME LIKE ? OR FIRST_NAME LIKE ? OR LAST_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHERS";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, [ $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, []))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/admin/listhashers3',
    methods: ['POST']
  )]
  public function getHashersParticipationListJson() {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($_POST['kennel_abbreviation']);
    $hashKy = $_POST['hash_key'];

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------

    #Validate input start
    if(!is_numeric($inputStart)){
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    $hashersAlreadyAddedToEventSql = "
      SELECT HASHER_KY
        FROM HASHINGS WHERE HASH_KY = ?";

    #Define the sql that performs the filtering
    $sql = "
      SELECT HASHER_NAME AS NAME, HASHER_KY AS THE_KEY, FIRST_NAME, LAST_NAME, HASHER_ABBREVIATION, (
             SELECT COUNT(*)
               FROM HASHINGS
              WHERE HASHINGS.HASHER_KY = HASHERS.HASHER_KY
                AND HASHINGS.HASH_KY != ?
                AND HASHINGS.HASH_KY IN (
                    SELECT HASH_KY
                      FROM HASHES_TABLE
                     WHERE KENNEL_KY = ?
                       AND EVENT_DATE > DATE_SUB((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 3 MONTH)
                       AND EVENT_DATE < DATE_ADD((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 3 MONTH))) AS RECENT_HASH_COUNT, (
                    SELECT COUNT(*)
                      FROM HASHINGS
                     WHERE HASHINGS.HASHER_KY = HASHERS.HASHER_KY
                       AND HASHINGS.HASH_KY != ?
                       AND HASHINGS.HASH_KY IN (
                           SELECT HASH_KY
                             FROM HASHES_TABLE
                            WHERE KENNEL_KY = ?
                              AND EVENT_DATE > DATE_SUB((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 1 YEAR)
                              AND EVENT_DATE < DATE_ADD((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 1 YEAR))) AS SORTA_RECENT_HASH_COUNT
        FROM HASHERS
       WHERE DECEASED = 0
         AND BANNED = 0
         AND HASHER_KY NOT IN (".$hashersAlreadyAddedToEventSql.")
         AND ( HASHER_NAME LIKE ? OR FIRST_NAME LIKE ? OR LAST_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
       ORDER BY RECENT_HASH_COUNT DESC, SORTA_RECENT_HASH_COUNT DESC
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHERS
       WHERE DECEASED = 0
         AND BANNED = 0
         AND HASHER_KY NOT IN (".$hashersAlreadyAddedToEventSql.")
         AND (HASHER_NAME LIKE ? OR FIRST_NAME LIKE ? OR LAST_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHERS
       WHERE DECEASED = 0
         AND BANNED = 0
         AND HASHER_KY NOT IN (".$hashersAlreadyAddedToEventSql.")";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search

    $theResults = $this->fetchAll($sql, [ $hashKy, $kennelKy, $hashKy, $hashKy, $hashKy, $kennelKy, $hashKy, $hashKy, $hashKy,
      $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $hashKy ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $hashKy,
       $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/admin/hasherDetailsKennelSelection/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function hasherDetailsKennelSelection(int $hasher_id) {

    #Obtain the kennels that are being tracked in this website instance
    $listOfKennelsSQL = "SELECT * FROM KENNELS WHERE IN_RECORD_KEEPING = 1";
    $kennelValues = $this->fetchAll($listOfKennelsSQL);

    if(count($kennelValues) == 1) {
      return new RedirectResponse("/" .
        $kennelValues[0]['KENNEL_ABBREVIATION'] .  "/hashers/" . $hasher_id);
    }

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    # Derive the hasher name
    $hasherName = $hasher['HASHER_NAME'];

    return $this->render('hasher_details_select_kennel.twig', [
      'pageTitle' => 'Hasher Details: Select Kennel',
      'kennelValues' => $kennelValues,
      'hasherId' => $hasher_id,
      'hasherName' => $hasherName ]);
  }

  #[Route('/admin/roster',
    methods: ['GET']
  )]
  #[Route('/admin/{kennel_abbreviation}/roster',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function roster(Request $request, string $kennel_abbreviation = null) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = $kennels[0]['KENNEL_KY'];
      } else {
        return $this->render('admin_select_kennel.twig', [
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'roster']);
      }
    }

    // Start with 5 minimum hashes in the last 6 months...
    // if <15 results, widen the search
    for($j=5; $j>0; $j--) {
      for($i=1; $i<3; $i++) {

        #Define the SQL to execute
        $sql = "
          SELECT HASHER_NAME
            FROM HASHERS
           WHERE HASHERS.HASHER_KY IN (
                 SELECT HASHER_KY
                   FROM HASHINGS
                  WHERE HASH_KY IN (
                        SELECT HASH_KY
                          FROM HASHES
                         WHERE EVENT_DATE >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                           AND KENNEL_KY = ?)
                  GROUP BY HASHER_KY
                 HAVING COUNT(*) >= ?)
           ORDER BY HASHER_NAME";

        #Execute the SQL statement; create an array of rows
        $theList = $this->fetchAll($sql, array($i * 6, $kennelKy, $j));

        if(count($theList) > 15) break;
      }
      if(count($theList) > 15) break;
    }

    return $this->render('admin_roster.twig', [ 'theList' => $theList ]);
  }

  public function legacy(Request $request, string $kennel_abbreviation = null) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = (int) $kennels[0]['KENNEL_KY'];
        $kennel_abbreviation = $kennels[0]['KENNEL_ABBREVIATION'];
      } else {
        return $this->render('admin_select_kennel.twig',array(
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'legacy'));
      }
    }

    $sql = "
      SELECT HASHERS.HASHER_KY, HASHERS.HASHER_NAME,
             COALESCE(LEGACY_HASHINGS.LEGACY_HASHINGS_COUNT, 0) AS LEGACY_HASHINGS_COUNT
        FROM HASHERS
   LEFT JOIN LEGACY_HASHINGS
          ON LEGACY_HASHINGS.HASHER_KY = HASHERS.HASHER_KY
       WHERE LEGACY_HASHINGS.KENNEL_KY IS NULL OR LEGACY_HASHINGS.KENNEL_KY = ?
       ORDER BY HASHERS.HASHER_NAME";

    #Execute the SQL statement; create an array of rows
    $theList = $this->fetchAll($sql, array($kennelKy));

    # Establish and set the return value
    $returnValue = $this->render('admin_legacy_hashings.twig',array(
      'theList' => $theList,
      'pageTitle' => 'Legacy Hashing Counts',
      'tableCaption' => 'Legacy Hashing Counts',
      'kennelAbbreviation' => $kennel_abbreviation,
      'csrf_token' => $this->getCsrfToken('legacy')
    ));

    #Return the return value
    return $returnValue;
  }

  private function processLegacyCountChange(Request $request, int $kennelKy, int $k, int $c) {

    if($c == 0) {
      $sql = "DELETE FROM LEGACY_HASHINGS WHERE HASHER_KY = ? AND KENNEL_KY = ?";
      $this->dbw->executeUpdate($sql, array($k, $kennelKy));
    } else {
      $sql = "UPDATE LEGACY_HASHINGS SET LEGACY_HASHINGS_COUNT = ? WHERE HASHER_KY = ? AND KENNEL_KY = ?";
      if($this->dbw->executeUpdate($sql, array($c, $k, $kennelKy)) == 0) {
        $sql = "SELECT 'exists' AS x FROM LEGACY_HASHINGS WHERE HASHER_KY = ? AND KENNEL_KY = ?";
        if($this->fetchOne($sql, array($k, $kennelKy)) != 'exists') {
          $sql = "INSERT INTO LEGACY_HASHINGS(LEGACY_HASHINGS_COUNT, HASHER_KY, KENNEL_KY) VALUES(?,?,?)";
          $this->dbw->executeUpdate($sql, array($c, $k, $kennelKy));
        }
      }
    }
    $actionType = "Legacy Hash Count Change";
    $actionDescription = "$kennelKy $k $c";
    $this->auditTheThings($request, $actionType, $actionDescription);
  }

  public function legacyUpdate(Request $request, string $kennel_abbreviation) {

    $token = $request->request->get('csrf_token');
    $this->validateCsrfToken('legacy', $token);

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $k = $request->request->get('k');
    $c = $request->request->get('c');

    if(is_array($k)) {
      for($i=0; $i < count($k); $i++) {
        $this->processLegacyCountChange($request, $kennelKy, $k[$i], $c[$i]);
      }
    } else {
      $this->processLegacyCountChange($request, $kennelKy, $k, $c);
    }
    return new Response("OK", 200, array('Content-Type' => 'text/plain'));
  }

  private function getKennels() {
    $sql = "
      SELECT KENNEL_KY, KENNEL_ABBREVIATION
        FROM KENNELS
       WHERE IN_RECORD_KEEPING = 1
       ORDER BY KENNEL_ABBREVIATION";

    return $this->fetchAll($sql, array());
  }

  #[Route('/admin/awards/{award_type}',
    methods: ['GET'],
    requirements: [
      'award_type' => '%app.pattern.award_type%']
  )]
  #[Route('/admin/{kennel_abbreviation}/awards/{award_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'award_type' => '%app.pattern.award_type%']
  )]
  #[Route('/admin/{kennel_abbreviation}/awards/{award_type}/{horizon}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'horizon' => '%app.pattern.horizon%',
      'award_type' => '%app.pattern.award_type%']
  )]
  public function awards(Request $request, string $kennel_abbreviation = null, string $award_type, int $horizon = -1) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = $kennels[0]['KENNEL_KY'];
        $kennel_abbreviation = $kennels[0]['KENNEL_ABBREVIATION'];
      } else {
        return $this->render('admin_select_kennel.twig', [
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'awards/'.$award_type ]);
      }
    }

    if($horizon == -1) {
      $horizon = $this->getDefaultAwardEventHorizon();
    }

    if($award_type == "pending") {
      $sql = "
        SELECT THE_KEY, NAME, VALUE,
               HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED AS LAST_AWARD,
               MAX(AWARD_LEVELS.AWARD_LEVEL) AS NEXT_AWARD_LEVEL
          FROM (".$this->getHashingCountsQuery().") HASHER_COUNTS
          LEFT JOIN HASHER_AWARDS
            ON HASHER_COUNTS.THE_KEY = HASHER_AWARDS.HASHER_KY
           AND HASHER_COUNTS.KENNEL_KY = HASHER_AWARDS.KENNEL_KY
          JOIN AWARD_LEVELS
            ON AWARD_LEVELS.KENNEL_KY = HASHER_COUNTS.KENNEL_KY
         WHERE AWARD_LEVELS.AWARD_LEVEL > COALESCE(HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED, 0)
           AND (VALUE + ?) >= AWARD_LEVELS.AWARD_LEVEL
         GROUP BY THE_KEY, NAME, VALUE, HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED
         ORDER BY VALUE DESC, NAME";
      $hasherList = $this->fetchAll($sql, [ $kennelKy, $kennelKy, $horizon ]);
    } else {
      $sql = "
        SELECT THE_KEY, NAME, VALUE,
               HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED AS LAST_AWARD
          FROM (".$this->getHashingCountsQuery().") HASHER_COUNTS
          LEFT JOIN HASHER_AWARDS
            ON HASHER_COUNTS.THE_KEY = HASHER_AWARDS.HASHER_KY
           AND HASHER_COUNTS.KENNEL_KY = HASHER_AWARDS.KENNEL_KY
         GROUP BY THE_KEY, NAME, VALUE, HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED
         ORDER BY VALUE DESC, NAME";
      $hasherList = $this->fetchAll($sql, [ $kennelKy, $kennelKy ]);
    }

    return $this->render('admin_awards.twig', [
      'pageTitle' => ($award_type=="pending" ? 'Pending' : 'All')." Hasher Awards",
      'tableCaption' => $award_type=="pending" ? 'Hashers, awards due, and last awards given.  Click the checkbox when a hasher receives the award they are due.' :
        'All hashers and the last award they received.',
      'subTitle' => $award_type=="pending" ? "All hashers that are due to receive an award." : "All hashers and the last award they have received.",
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'kennel_key' => $kennelKy,
      'pageTracking' => 'Hasher Awards',
      'award_type' => $award_type,
      'horizon' => $horizon,
      'csrf_token' => $this->getCsrfToken('awards') ]);
  }

  #[Route('/admin/updateHasherAward',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function updateHasherAwardAjaxAction(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('awards', $token);

    #Obtain the post values
    $hasherKey = $_POST['hasher_key'];
    $kennelKey = $_POST['kennel_key'];
    $awardLevel = $_POST['award_level'];

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey) & ctype_digit($kennelKey)) {

      $sql = "SELECT 1 FROM HASHER_AWARDS WHERE HASHER_KY = ? AND KENNEL_KY = ?";

      $exists = $this->fetchAssoc($sql, [ $hasherKey, $kennelKey ]);

      if($exists) {
        $sql = "UPDATE HASHER_AWARDS SET LAST_AWARD_LEVEL_RECOGNIZED = ? WHERE HASHER_KY = ? AND KENNEL_KY = ?";
      } else {
        $sql = "INSERT INTO HASHER_AWARDS(LAST_AWARD_LEVEL_RECOGNIZED, HASHER_KY, KENNEL_KY) VALUES(?,?,?)";
      }

      try {
        $this->getWriteConnection()->executeUpdate($sql, [ $awardLevel, $hasherKey, $kennelKey ]);

        $returnMessage = "Success!";
      } catch (\Exception $theException) {

        $tempActionType = "Update Hasher Award";
        $tempActionDescription = "Failed to update hasher award for $hasherKey";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

        $returnMessage = "Oh crap. Something bad happened.";
      }
    }

    $actionType = "Update Hasher Award";
    $actionDescription = "Award level $awardLevel set for $hasherKey";
    $this->auditTheThings($request, $actionType, $actionDescription);

    return new JsonResponse($returnMessage);
  }
}
