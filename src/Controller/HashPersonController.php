<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Hasher;
use App\Entity\ModifyHasherTask;
use App\SqlQueries;
use App\Repository\HasherRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class HashPersonController extends BaseController
{
  private SqlQueries $sqlQueries;

  public function __construct(ManagerRegistry $doctrine, SqlQueries $sqlQueries) {
    parent::__construct($doctrine);
    $this->sqlQueries = $sqlQueries;
  }

  #[Route('/admin/deleteHasher/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function deleteHashPersonPreAction(Request $request, int $hasher_id) {

    # Make a database call to obtain the hasher information
    $sql = "SELECT * FROM HASHERS WHERE HASHER_KY = ?";
    $hasherValue = $this->fetchAssoc($sql, [ $hasher_id ]);

    #Determine if the hasher exists
    if(!$hasherValue) {
      $hasherExists = False;
      $pageSubTitle = "This hasher does not exist!";
    } else {
      $hasherExists = True;
      $pageSubTitle = "There is no going back!";
    }

    # Obtain all of their hashings (all kennels)
    $allHashings = $this->fetchAll($this->sqlQueries->getAllHashingsInAllKennelsForHasher(), [ $hasher_id ]);

    # Obtain all of their harings (all kennels)
    $allHarings = $this->fetchAll($this->sqlQueries->getAllHaringsInAllKennelsForHasher(), [ $hasher_id ]);

    # Establish the return value
    return $this->render('admin_delete_hasher.twig', [
      'pageTitle' => 'Hasher Deletion',
      'pageSubTitle' => $pageSubTitle,
      'theirHashings' => $allHashings,
      'theirHarings' => $allHarings,
      'theirHaringCount' => count($allHarings),
      'theirHashingCount' => count($allHashings),
      'hasher_id' => $hasher_id,
      'hasher_value' => $hasherValue,
      'hasher_exists' => $hasherExists,
      'csrf_token' => $this->getCsrfToken('delete'.$hasher_id) ]);
  }

  #[Route('/admin/deleteHasherPost',
    methods: ['POST'],
  )]
  public function deleteHashPersonAjaxAction(Request $request) {

    #Establish the return message
    $returnMessage = "This has not been set yet...";

    #Obtain the post values
    $hasherKey = $_POST['hasher_key'];

    #Obtain the csrf token
    $token = $request->request->get('csrf_token');
    $this->validateCsrfToken('delete'.$hasherKey, $token);

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey)) {

      #1. Ensure this hasher exists
      #Determine the hasher identity
      $hasherIdentitySql = "SELECT * FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

      # Make a database call to obtain the hasher information
      $hasherValue = $this->fetchAssoc($hasherIdentitySql, [ $hasherKey ]);

      #2. Ensure they have no hashings
      $hasHashingsSQL = "SELECT 1 AS X FROM HASHINGS WHERE HASHER_KY = ?";
      $hashingsList = $this->fetchAll($hasHashingsSQL, [ $hasherKey ]);
      $hashingCount = count($hashingsList);

      #3. Ensure they have no harings
      $hasHaringsSQL = "SELECT 1 AS X FROM HARINGS WHERE HARINGS_HASHER_KY = ?";
      $haringsList = $this->fetchAll($hasHaringsSQL, [ $hasherKey ]);
      $haringCount = count($haringsList);

      #If the hasher exists
      if($hasherValue != null) {

        #Set the name of the hasher
        $hasherName = $hasherValue['HASHER_NAME'];

        #If the hasher still has hashings
        if($hashingCount == 0 && $haringCount ==0){

          $returnMessage = "Success! Hasher has $hashingCount hashings and $haringCount harings. You may delete them.";

          #Define the deletion sql statement
          $deletionSQL = "DELETE FROM HASHERS WHERE HASHER_KY=?";

          try{
            #Execute the query
            $this->getWriteConnection()->executeUpdate($deletionSQL, [ $hasherKey ]);

            #Audit the action
            $hasherNickname = $hasherValue['HASHER_ABBREVIATION'];
            $hasherFirstName = $hasherValue['FIRST_NAME'];
            $hasherLastName = $hasherValue['LAST_NAME'];
            $hasherHomeKennel = $hasherValue['HOME_KENNEL'];
            $hasherDeceased = $hasherValue['DECEASED'];
            $tempActionType = "Delete Person";
            $tempActionDescription = "Deleted ($hasherName|$hasherNickname|$hasherFirstName|$hasherLastName|$hasherHomeKennel|$hasherDeceased)";
            $this->auditTheThings($request, $tempActionType, $tempActionDescription);

            #Define the return message
            $returnMessage = "Success! They are gonzo!";
          } catch (\Exception $theException){

            $tempActionType = "Delete Person";
            $tempActionDescription = "Failed to delete $hasherName";
            $this->auditTheThings($request, $tempActionType, $tempActionDescription);

            #Define the return message
            $returnMessage = "Oh crap. Something bad happened.";
          }
        } else {
          $returnMessage = "Hasher has $hashingCount hashings and $haringCount harings. You cannot delete them.";
        }

      } else {
        $returnMessage = "The hasher ($hasherKey) does not exist.";
      }

    } else {
      $returnMessage = "The hasher key ($hasherKey) is invalid.";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/modifyhasher/form/{hasher_id}',
    methods: ['GET','POST'],
    requirements: [
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function modifyHashPersonAction(Request $request, int $hasher_id, HasherRepository $hasherRepository) {

    $hasher = $hasherRepository->findOneBy([ 'hasher_ky' => $hasher_id ]);

    $task = new ModifyHasherTask();
    $tempHasherName = $hasher->getHasherName();
    $task->setHasherName($tempHasherName);
    $task->setHasherAbbreviation($hasher->getHasherAbbreviation());
    $task->setLastName($hasher->getLastName());
    $task->setFirstName($hasher->getFirstName());
    $task->setHomeKennel($hasher->getHomeKennel());
    $task->setBanned($hasher->getBanned());
    $task->setDeceased($hasher->getDeceased());

    $form = $this->createFormBuilder($task)
      ->add('hasherName', TextType::class, array('label' => 'Hasher Name'))
      ->add('hasherAbbreviation', TextType::class, array('label' => 'Hasher Abbreviation'))
      ->add('lastName', TextType::class, array('label' => 'Last Name'))
      ->add('firstName', TextType::class, array('label' => 'First Name'))
      ->add('homeKennel', TextType::class, array('label' => 'Home Kennel'))
      ->add('banned', ChoiceType::class, array('label' => 'Banned',
        'choices'  => array('No' => '0', 'Yes, strike their name from all pylons and obelisks' => '1')))
      ->add('deceased', ChoiceType::class, array('label' => 'Deceased',
        'choices'  => array('No' => '0', 'Yes, let us cherish their memory' => '1')))
      ->setAction('#')
      ->setMethod('POST')
      ->getForm();

    $form->handleRequest($request);

    if($request->getMethod() == 'POST') {

      if ($form->isValid()) {
          #Obtain the name/value pairs from the form
          $task = $form->getData();

          $hasher->setHasherName($task->getHasherName());
          $hasher->setHasherAbbreviation($task->getHasherAbbreviation());
          $hasher->setLastName($task->getLastName());
          $hasher->setFirstName($task->getFirstName());
          $hasher->setHomeKennel($task->getHomeKennel());
          $hasher->setBanned($task->getBanned());
          $hasher->setDeceased($task->getDeceased());

          $hasherRepository->save($hasher, true);

          #Add a confirmation that everything worked
          $this->addFlash('success', 'Success! You modified the person. They were not good enough as they were, so you made them better.');

          #Audit the action
          $tempActionType = "Modify Person";
          $tempActionDescription = "Modified $tempHasherName";
          $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      } else{
        $this->addFlash('danger', 'Wrong! You broke it.');
      }
    }

    return $this->render('edit_hasher_form.twig', [
      'pageTitle' => 'Hasher Person Modification',
      'pageHeader' => 'All fields are required',
      'form' => $form->createView(),
      'hasher_ky' => $hasher_id,
    ]);
  }

  #Define the action
  public function createHashPersonAction(Request $request){

    $formFactoryThing = $this->container->get('form.factory')->createBuilder(FormType::class)
      ->add('HASHER_NAME', TextType::class, array('label' => 'Hasher Name'))
      ->add('HASHER_ABBREVIATION', TextType::class, array('label' => 'Hasher Abbreviation'))
      ->add('LAST_NAME', TextType::class, array('label' => 'Last Name'))
      ->add('FIRST_NAME', TextType::class, array('label' => 'First Name'))
      ->add('HOME_KENNEL', TextType::class, array('label' => 'Home Kennel'))
      ->add('DECEASED', ChoiceType::class, array('label' => 'Deceased',
        'choices'  => array('No' => '0', 'Yes, let us cherish their memory' => '1')));


    $formFactoryThing->setAction('#');
    $formFactoryThing->setMethod('POST');
    $form=$formFactoryThing->getForm();


    $form->handleRequest($request);

    if($request->getMethod() == 'POST'){

      if ($form->isValid()) {
          #Obtain the name/value pairs from the form
          $data = $form->getData();

          #Establish the values from the form
          $tempHasherName = $data['HASHER_NAME'];
          $tempHasherAbbreviation = $data['HASHER_ABBREVIATION'];
          $tempLastName = $data['LAST_NAME'];
          $tempFirstName = $data['FIRST_NAME'];
          $tempHomeKennel = $data['HOME_KENNEL'];
          $tempDeceased = $data['DECEASED'];


          $sql = "
            INSERT INTO HASHERS (
              HASHER_NAME,
              HASHER_ABBREVIATION,
              LAST_NAME,
              FIRST_NAME,
              HOME_KENNEL,
              DECEASED
            ) VALUES (?, ?, ?, ?, ?, ?)";


          $this->dbw->executeUpdate($sql,array(
            $tempHasherName,
            $tempHasherAbbreviation,
            $tempLastName,
            $tempFirstName,
            $tempHomeKennel,
            $tempDeceased
          ));


          #Add a confirmation that everything worked
          $theSuccessMessage = "Success! You created a person. (Hasher $tempHasherName)";
          $this->container->get('session')->getFlashBag()->add('success', $theSuccessMessage);

          #Audit the action
          $tempActionType = "Create Person";
          $tempActionDescription = "Created $tempHasherName";
          $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      } else{
        $this->container->get('session')->getFlashBag()->add('danger', 'Wrong! You broke it.');
      }

    }

    $returnValue = $this->render('new_hasher_form.twig', array (
      'pageTitle' => 'Hasher Person Creation',
      'pageHeader' => 'All fields are required',
      'form' => $form->createView(),
    ));

    #Return the return value
    return $returnValue;

  }


  #[Route('/{kennel_abbreviation}/hashers/retrieve',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function retrieveHasherAction() {

    #Obtain the post values
    $hasherKey = (int) $_POST['hasher_key'];

    #Determine the hasher identity
    $hasherIdentitySql = "SELECT HASHER_NAME FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

    # Make a database call to obtain the hasher information
    $hasherValue = $this->fetchAssoc($hasherIdentitySql, [ $hasherKey ]);

    #Obtain the hasher name from the object
    return new JsonResponse($hasherValue['HASHER_NAME']);
  }
}
