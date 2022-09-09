<?php

namespace App\Controller;

use App\Entity\Task;
use Symfony\Component\Mime\Email;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Security\LoginAuthenticator;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class HomeController extends AbstractController
{
    
    
    public function __construct(EntityManagerInterface $manager, UserRepository $userRepository,TaskRepository $taskRepository)
    {
        $this->em=$manager;
        $this->user=$userRepository;
        $this->task=$taskRepository;
    }
    
    #[Route('/', name: 'app_home', methods:'GET')]
    public function index(): Response
    {
        if($this->getUser())
        {
            $tasks=$this->task->findAllTask($this->getUser());
     
            return $this->render('home/home.html.twig',[
                "tasks"=>$tasks
            ]); 
        }

        

            return $this->redirectToRoute('app_login');

        

        

    }

    #[Route('/createtask', name: 'create_task', methods:'POST')]
    public function createTask(Request $request): Response
    {
        if($this->getUser())
        {
        //get the task send by the form
        $getTask=$request->request->get('text');
        
        $newTask=new Task();

        $newTask->setTitle($getTask)
                ->setUser($this->getUser());
    

        $this->em->persist($newTask);

        $this->em->flush();

         return $this->redirectToRoute('app_home');

        }

        return $this->redirectToRoute('app_login');
       
    }


    #[Route('/task/{id}/edit', name: 'task_edit', methods:'GET')]
    public function taskEdit($id): Response
    {
        if($this->getUser())
        {
        $getTask=$this->task->find($id);

        $task=$getTask;

        return $this->render('edit/edit.html.twig',[
            "task"=>$task
        ]);

       }

       
       return $this->redirectToRoute('app_login');
    }

    #[Route('/edit/{id}', name: 'edit_task', methods:'POST')]
    public function editTask(Request $request,$id): Response
    {
        if($this->getUser())
        {
        $getText=$request->request->get('text');
        
        $task=$this->task->find($id);
        
        $task=$task->setTitle($getText);

        $this->em->flush();
       
        return $this->redirectToRoute('app_home');

        }
        return $this->redirectToRoute('app_login');


    }

    #[Route('/delete/{id}', name: 'delete_task', methods:'GET')]
    public function deleteTask($id): Response
    {
        if($this->getUser())
        {
            $getTask=$this->task->find($id);

            $this->em->remove($getTask);

            $this->em->flush();
            
            return $this->redirectToRoute('app_home');

        }
         return $this->redirectToRoute('app_login');
    
    }


    #[Route('/forget', name: 'app_forget')]
    public function forget(): Response
    {
        return $this->render('forget/forget.html.twig');
    }

    #[Route('/forgetPassword', name: 'forget_password', methods:'POST')]
    public function forgetPass(Request $request, TokenGeneratorInterface $tokenGenerator): Response
    { 
       $getEmail=$request->request->get('email');
      
       
       $getUrl='https://127.0.0.1:8000';

       //Check if email exists

       $checkEmail=$this->user->findOneByEmail($getEmail);
      
       if(!$checkEmail)
       {
           return $this->render('forget/forget.html.twig',
       [
           'message'=>'This email doesn\'t exist !'
       ]);

       }
       //get the name of the user
       $getName=$checkEmail->getName();
       //generate the token
       $token= $tokenGenerator->generateToken();
       // store the token in database
       $checkEmail->setToken($token);

       $this->em->flush();

       //Generate the html

       $html=<<<HTMLBody
         
       <h1 style="text-align: center; color:blue;">TODO- APP</h1>
         Hello $getName,
         <br />
         we receive a notification that you forgot your password,
         Please click on the link to recover your password:
         <tr>
                      <td height="24" style="height:24px"></td>
                    </tr>
                    <tr>
                      <td align="center" valign="middle">
                             <table width="217" border="0" align="center" cellpadding="0" cellspacing="0" style="background: #34a5f8; width:217px;border-radius: 5px;">
                            <tr>
                               <td width="217" height="29" align="center" style="font-family:Arial,sans-serif;font-size:15px; line-height:15px;"><a href="https://127.0.0.1:8000/pass?token=' . $token . '&email=' . $getEmail . '" target="_blank" style="text-decoration:none;color:#ffffff; display:block; padding:10px; "><strong>Go to the app</strong></a></td>
                            </tr>
                          </table>
                      </td>
                    </tr>


       HTMLBody;

       //Create the mail
       $email = (new Email())
       ->from('codeurproject@gmail.com')
       ->to($getEmail)
       ->subject('Forget password!')
       ->html($html);

        $dsn=$this->getParameter('app_dsn');

        $transport= Transport::fromDsn($dsn);

        $mailer= new Mailer($transport);

        $mailer->send($email);

     
      return $this->render('forget/forget.html.twig',[
       "message"=>"An email was send to you, please check your email account"
     ]);



    }

    #[Route('/pass', name: 'recover_password', methods:'GET')]
    public function pass(Request $request): Response
    {
      $value=$request->getRequestUri();
      $token=substr($value,22,43);
      
      //check if the user with this token exists
      $checkUser=$this->user->findBy(["token"=>$token]);
      if($checkUser)
      {
        $checkUser[0]->setToken("");
        $this->em->flush();

        return $this->render('reset/reset.html.twig',
       [
           'message'=>$checkUser[0]->getName().''.', choose a new password !',
           'idUser'=>$checkUser[0]->getId()
       ]);

      }
       
         return $this->redirectToRoute('app_login');

    }

    #[Route('/reset/{id}', name: 'reset_password', methods:'POST')]
    public function resetPassword(Request $request,$id, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, LoginAuthenticator $authenticator): Response
    {
        $getPassword=$request->request->get('password');
        $getConfirmPassword=$request->request->get('confirm_password');

        $user=$this->user->find($id);

         
        if($getPassword !=$getConfirmPassword)
        {
            return $this->render('reset/reset.html.twig',
            [
                'error_password'=>'Passwords don\'t match!',
                'message'=>$user->getName().''.', choose a new password !',
                'idUser'=>$id
                
            ]);
        }

        $user->setPassword($userPasswordHasher->hashPassword($user,$getPassword));

        $this->em->flush();

        return $userAuthenticator->authenticateUser(
        $user,
        $authenticator,
        $request
        );

    }

}
