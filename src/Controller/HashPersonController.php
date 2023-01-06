<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\SqlQueries;
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

  public function deleteHashPersonPreAction(Request $request, int $hasher_id){

    # Make a database call to obtain the hasher information
    $sql = "SELECT * FROM HASHERS WHERE HASHER_KY = ?";
    $hasherValue = $this->fetchAssoc($sql, array((int) $hasher_id));

    #Determine if the hasher exists
    if(!$hasherValue){
      $hasherExists = False;
      $pageSubTitle = "This hasher does not exist!";
    }else{
      $hasherExists = True;
      $pageSubTitle = "There is no going back!";
    }

    # Obtain all of their hashings (all kennels)
    $allHashings = $this->fetchAll(ALL_HASHINGS_IN_ALL_KENNELS_FOR_HASHER, array((int)$hasher_id));

    # Obtain all of their harings (all kennels)
    $allHarings = $this->fetchAll(ALL_HARINGS_IN_ALL_KENNELS_FOR_HASHER, array((int)$hasher_id));

    # Establish the return value
    $returnValue = $this->render('admin_delete_hasher.twig',array(
      'pageTitle' => 'Hasher Deletion',
      'pageSubTitle' => $pageSubTitle,
      'theirHashings' => $allHashings,
      'theirHarings' => $allHarings,
      'theirHaringCount' => count($allHarings),
      'theirHashingCount' => count($allHashings),
      'hasher_id' => $hasher_id,
      'hasher_value' => $hasherValue,
      'hasher_exists' => $hasherExists,
      'csrf_token' => $this->getCsrfToken('delete'.$hasher_id)
    ));

    #Return the return value
    return $returnValue;
  }

  public function deleteHashPersonAjaxAction(Request $request){

    #Establish the return message
    $returnMessage = "This has not been set yet...";

    #Obtain the post values
    $hasherKey = $request->request->get('hasher_key');

    #Obtain the csrf token
    $token = $request->request->get('csrf_token');
    $this->validateCsrfToken('delete'.$hasherKey, $token);

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey)){

      #1. Ensure this hasher exists
      #Determine the hasher identity
      $hasherIdentitySql = "SELECT * FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

      # Make a database call to obtain the hasher information
      $hasherValue = $this->fetchAssoc($hasherIdentitySql, array((int) $hasherKey));

      #2. Ensure they have no hashings
      $hasHashingsSQL = "SELECT * FROM HASHINGS JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY WHERE HASHERS.HASHER_KY = ?";
      $hashingsList = $this->fetchAll($hasHashingsSQL,array((int)$hasherKey));
      $hashingCount = count($hashingsList);


      #3. Ensure they have no harings
      $hasHaringsSQL = "SELECT * FROM HARINGS JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY WHERE HASHERS.HASHER_KY = ?";
      $haringsList = $this->fetchAll($hasHaringsSQL,array((int)$hasherKey));
      $haringCount = count($haringsList);

      #If the hasher exists
      if($hasherValue != null){

        #Set the name of the hasher
        $hasherName = $hasherValue['HASHER_NAME'];


        #If the hasher still has hashings
        if($hashingCount == 0 && $haringCount ==0){

          $returnMessage = "Success! Hasher has $hashingCount hashings and $haringCount harings. You may delete them.";

          #Define the deletion sql statement
          $deletionSQL = "DELETE FROM HASHERS WHERE HASHER_KY=?";

          try{
            #Execute the query
            $this->dbw->executeUpdate($deletionSQL,array((int) $hasherKey));

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


  public function modifyHashPersonAction(Request $request, int $hasher_id){

    # Declare the SQL used to retrieve this information
    $sql = "SELECT * FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasherValue = $this->fetchAssoc($sql, array((int) $hasher_id));

    $data = array(
        'HASHER_KY' => $hasherValue['HASHER_KY'],
        'HASHER_NAME' => $hasherValue['HASHER_NAME'],
        'HASHER_ABBREVIATION' => $hasherValue['HASHER_ABBREVIATION'],
        'LAST_NAME' => $hasherValue['LAST_NAME'],
        'FIRST_NAME' => $hasherValue['FIRST_NAME'],
        'HOME_KENNEL' => $hasherValue['HOME_KENNEL'],
        'DECEASED' => $hasherValue['DECEASED'],
        'BANNED' => $hasherValue['BANNED']
    );

    $formFactoryThing = $this->container->get('form.factory')->createBuilder(FormType::class, $data)
      ->add('HASHER_NAME', TextType::class, array('label' => 'Hasher Name'))
      ->add('HASHER_ABBREVIATION', TextType::class, array('label' => 'Hasher Abbreviation'))
      ->add('LAST_NAME', TextType::class, array('label' => 'Last Name'))
      ->add('FIRST_NAME', TextType::class, array('label' => 'First Name'))
      ->add('HOME_KENNEL', TextType::class, array('label' => 'Home Kennel'))
      ->add('BANNED', ChoiceType::class, array('label' => 'Banned',
        'choices'  => array('No' => '0', 'Yes, strike their name from all pylons and obelisks' => '1')))
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
          $tempBanned = $data['BANNED'];

          $sql = "
            UPDATE HASHERS
            SET
              HASHER_NAME= ?, HASHER_ABBREVIATION= ?, LAST_NAME= ?, FIRST_NAME=?, HOME_KENNEL=?, DECEASED=?, BANNED=?
            WHERE HASHER_KY=?";
          $this->dbw->executeUpdate($sql,array(
            $tempHasherName,
            $tempHasherAbbreviation,
            $tempLastName,
            $tempFirstName,
            $tempHomeKennel,
            $tempDeceased,
            $tempBanned,
            $hasher_id
          ));

          #Add a confirmation that everything worked
          $this->container->get('session')->getFlashBag()->add('success', 'Success! You modified the person. They were not good enough as they were, so you made them better.');

          #Audit the action
          $tempActionType = "Modify Person";
          $tempActionDescription = "Modified $tempHasherName";
          $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      } else{
        $this->container->get('session')->getFlashBag()->add('danger', 'Wrong! You broke it.');
      }

    }

    $returnValue = $this->render('edit_hasher_form.twig', array (
      'pageTitle' => 'Hasher Person Modification',
      'pageHeader' => 'All fields are required',
      'form' => $form->createView(),
      'hasherValue' => $hasherValue,
    ));

    #Return the return value
    return $returnValue;

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


  #[Route('/{kennel_abbreviation}/hashers/retrieve', methods: ['POST'], requirements: ['kennel_abbreviation' => '%app.pattern.kennel_abbreviation%'])]
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
