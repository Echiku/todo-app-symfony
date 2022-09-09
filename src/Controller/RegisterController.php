<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Security\LoginAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class RegisterController extends AbstractController
{
    
    public function __construct(EntityManagerInterface $manager, UserRepository $userRepository)
    {
        $this->em=$manager;
        $this->user=$userRepository;
         
    }
    
    #[Route('/register', name: 'app_register')]
    public function index(): Response
    {
        return $this->render('register/register.html.twig');
    }

    #[Route('/registerUser', name: 'user_register', methods:'POST')]
    public function registerUser(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, LoginAuthenticator $authenticator): Response
    {
        
        $getUsername=$request->request->get('email');
        $getPassword=$request->request->get('password');
        $getConfirmPassword=$request->request->get('confirm_password');
        $getName=$request->request->get('name');

        //Unicity of email
        $checkEmail=$this->user->findOneByEmail($getUsername);
        if($checkEmail)
        {
            return $this->render('register/register.html.twig',
        [
            'error_email'=>'This email already exists, please choose another one !'
        ]);

        }

        
        if($getPassword !=$getConfirmPassword)
        {
            return $this->render('register/register.html.twig',
            [
                'error_password'=>'Passwords don\'t match!'
            ]);
        }
        
        if(empty($checkEmail) && $getPassword === $getConfirmPassword)
        {
            
          $newUser= new User();
          $newUser->setEmail($getUsername)
                  ->setPassword($userPasswordHasher->hashPassword($newUser,$getPassword))
                  ->setName($getName);

          $this->em->persist($newUser);
          $this->em->flush();

          return $userAuthenticator->authenticateUser(
            $newUser,
            $authenticator,
            $request
        );

        }
      
        return $this->render('register/register.html.twig');

        //check if password are identical

        //Create new user

    }
}
