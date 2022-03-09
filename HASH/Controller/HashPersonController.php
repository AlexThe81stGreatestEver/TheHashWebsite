<?php

namespace HASH\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class HashPersonController extends BaseController
{

  public function __construct(Application $app) {
    parent::__construct($app);
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
      'csrf_token' => $this->getCsrfToken('delete')
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
    $this->validateCsrfToken('delete', $token);

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
            $this->app['dbs']['mysql_write']->executeUpdate($deletionSQL,array((int) $hasherKey));

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

    #Set the return value
    $returnValue =  $this->app->json($returnMessage, 200);
    return $returnValue;
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
    );

    $formFactoryThing = $this->app['form.factory']->createBuilder(FormType::class, $data)
      ->add('HASHER_NAME', TextType::class, array('label' => 'Hasher Name'))
      ->add('HASHER_ABBREVIATION', TextType::class, array('label' => 'Hasher Abbreviation'))
      ->add('LAST_NAME', TextType::class, array('label' => 'Last Name'))
      ->add('FIRST_NAME', TextType::class, array('label' => 'First Name'))
      ->add('HOME_KENNEL', TextType::class, array('label' => 'Home Kennel'))
      ->add('DECEASED', ChoiceType::class, array('label' => 'Deceased',
        'choices'  => array('No' => '0000000000', 'Yes, let us cherish their memory' => '0000000001')));

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
            UPDATE HASHERS
            SET
              HASHER_NAME= ?, HASHER_ABBREVIATION= ?, LAST_NAME= ?, FIRST_NAME=?, HOME_KENNEL=?, DECEASED=?
            WHERE HASHER_KY=?";
          $this->app['dbs']['mysql_write']->executeUpdate($sql,array(
            $tempHasherName,
            $tempHasherAbbreviation,
            $tempLastName,
            $tempFirstName,
            $tempHomeKennel,
            $tempDeceased,
            $hasher_id
          ));

          #Add a confirmation that everything worked
          $this->app['session']->getFlashBag()->add('success', 'Success! You modified the person. They were not good enough as they were, so you made them better.');

          #Audit the action
          $tempActionType = "Modify Person";
          $tempActionDescription = "Modified $tempHasherName";
          $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      } else{
        $this->app['session']->getFlashBag()->add('danger', 'Wrong! You broke it.');
      }

    }

    $returnValue = $this->render('edit_hasher_form.twig', array (
      'pageTitle' => 'Hasher Person Modification',
      'pageHeader' => 'Why is this so complicated ?',
      'form' => $form->createView(),
      'hasherValue' => $hasherValue,
    ));

    #Return the return value
    return $returnValue;

  }

  #Define the action
  public function createHashPersonAction(Request $request){

    $formFactoryThing = $this->app['form.factory']->createBuilder(FormType::class)
      ->add('HASHER_NAME', TextType::class, array('label' => 'Hasher Name'))
      ->add('HASHER_ABBREVIATION', TextType::class, array('label' => 'Hasher Abbreviation'))
      ->add('LAST_NAME', TextType::class, array('label' => 'Last Name'))
      ->add('FIRST_NAME', TextType::class, array('label' => 'First Name'))
      ->add('HOME_KENNEL', TextType::class, array('label' => 'Home Kennel'))
      ->add('DECEASED', ChoiceType::class, array('label' => 'Deceased',
        'choices'  => array('No' => '0000000000', 'Yes, let us cherish their memory' => '0000000001')));


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


          $this->app['dbs']['mysql_write']->executeUpdate($sql,array(
            $tempHasherName,
            $tempHasherAbbreviation,
            $tempLastName,
            $tempFirstName,
            $tempHomeKennel,
            $tempDeceased
          ));


          #Add a confirmation that everything worked
          $theSuccessMessage = "Success! You created a person. (Hasher $tempHasherName)";
          $this->app['session']->getFlashBag()->add('success', $theSuccessMessage);

          #Audit the action
          $tempActionType = "Create Person";
          $tempActionDescription = "Created $tempHasherName";
          $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      } else{
        $this->app['session']->getFlashBag()->add('danger', 'Wrong! You broke it.');
      }

    }

    $returnValue = $this->render('new_hasher_form.twig', array (
      'pageTitle' => 'Hasher Person Creation',
      'pageHeader' => 'Why is this so complicated ?',
      'form' => $form->createView(),
    ));

    #Return the return value
    return $returnValue;

  }


    public function retrieveHasherAction (Request $request){

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      #Obtain the post values
      $hasherKey = $request->request->get('hasher_key');


      #Validate the post values; ensure that they are both numbers
      if(ctype_digit($hasherKey)){

        #Determine the hasher identity
        $hasherIdentitySql = "SELECT * FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

        # Make a database call to obtain the hasher information
        $hasherValue = $this->fetchAssoc($hasherIdentitySql, array((int) $hasherKey));

        #Obtain the hasher name from the object
        $tempHasherName = $hasherValue['HASHER_NAME'];

        #Establish the return value
        $returnMessage = $tempHasherName;


      } else{
        $returnMessage = "Something is wrong with the input.$hasherKey";
      }

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;
    }



}
