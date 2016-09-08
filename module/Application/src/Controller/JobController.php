<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Form\Annotation\AnnotationBuilder;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Application\Service\ActivityStreamLogger;
use Application\Entity\Job;
use Application\Entity\Activity;

class JobController extends AbstractActionController
{
    private $em;
    
    public function __construct(EntityManager $em, ActivityStreamLogger $asl)
    {
        $this->em = $em;
        $this->asl = $asl;
    }

    public function indexAction()
    {
        $jobs = $this->em->getRepository(Job::class)->findBy(array(), array('created' => 'DESC'));
        return new ViewModel(array('jobs' => $jobs));
    }
    
    public function viewAction()
    {   
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('jobs');
        }
        $job = $this->em->getRepository(Job::class)->find($id);
        $activities = $this->em->getRepository(Activity::class)->findBy(
            array('jobId' => $job->getId()),
            array('created' => 'DESC')
        );
        return new ViewModel(array('job' => $job, 'activities' => $activities));
    }    
    
    public function saveAction()
    {   
        $id = (int) $this->params()->fromRoute('id', 0);
        $job = $this->em->getRepository(Job::class)->find($id);        
        if (!$job) {
            $job = new Job();
            $job->setCreated(new \DateTime("now"));
        }
        $builder = new AnnotationBuilder();
        $hydrator = new DoctrineHydrator($this->em);
        $form = $builder->createForm($job);
        $form->setHydrator($hydrator);
        $form->bind($job);
        $request = $this->getRequest();
        if ($request->isPost()){
            $form->setData($request->getPost());
            if ($form->isValid()){  
                $this->em->persist($job); 
                $this->em->flush();
                $this->asl->log(
                    $job->getEntityOperationType(), 
                    Activity::ENTITY_TYPE_JOB, 
                    $job->getId(), 
                    $job->getId(),
                    $job->getEntityChangeSet()
                );
                return $this->redirect()->toRoute('jobs');
            }
        }
         
        return new ViewModel(array(
            'form' => $form,
            'id' => $job->getId(),
        ));
    }
    
    public function deleteAction()
    {   
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('jobs');
        }
        $job = $this->em->getRepository(Job::class)->find($id);
        $this->em->remove($job);
        $this->em->flush();
        $this->asl->log(
            Activity::ENTITY_OPERATION_TYPE_DELETE, 
            Activity::ENTITY_TYPE_JOB, 
            $id, 
            $id
        );        
        return $this->redirect()->toRoute('jobs');
    }
    
}
