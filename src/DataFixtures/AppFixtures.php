<?php
namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
# Entité User
use App\Entity\User;
# Entité Post
use App\Entity\Post;
# Entité Section
use App\Entity\Section;
# Entité Comment
use App\Entity\Comment;
# Entité Tag
use App\Entity\Tag;

# chargement du hacher de mots de passe
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

# chargement de Faker et Alias de nom
# pour utiliser Faker plutôt que Factory
# comme nom de classe
use Faker\Factory AS Faker;
use Faker\Generator;
use Symfony\Component\String\Slugger\AsciiSlugger;

class AppFixtures extends Fixture
{
    // Attribut privé contenant le hacheur de mot de passe
    private UserPasswordHasherInterface $hasher;
    private AsciiSlugger $slugger;
    private Generator $faker;

    // création d'un constructeur pour récupérer le hacher
    // de mots de passe
    public function __construct(UserPasswordHasherInterface $userPasswordHasher){
        $this->hasher = $userPasswordHasher;
        $this->slugger = new AsciiSlugger();
        $this->faker = Faker::create('fr_FR');
    }

    private function createRandomUser(int $i): User{
        $user = new User();
        $user->setUsername('user'.$i);
        $pwdHash = $this->hasher->hashPassword($user, 'user'.$i);
        $user->setPassword($pwdHash);
        $lastName = $this->faker->lastName();
        $firstName = $this->faker->firstName();
        $user->setFullname("$lastName $firstName");
        $user->setUniqid(uniqid(more_entropy: true));
        $user->setEmail(strtolower($lastName).'_'.strtolower($firstName).'@gmail.com');
        $user->setActivate(!(mt_rand(1,4) === 4));

        return $user;
    }

    public function load(ObjectManager $manager): void
    {
        ###
        #
        # INSERTION de l'admin avec mot de passe admin
        #
        ###
        // création d'une instance de User
        $user = new User();

        // création de l'administrateur via les setters
        $user->setUsername('admin');
        $user->setRoles(['ROLE_ADMIN']);
        // on va hacher le mot de passe
        $pwdHash = $this->hasher->hashPassword($user, 'admin');
        // passage du mot de passe crypté
        $user->setPassword($pwdHash);
        $user->setFullname('Coucou Pomme');
        $user->setUniqid(uniqid(more_entropy: true));
        $user->setEmail("coucou_pomme@gmail.com");
        $user->setActivate(true);

        // on va mettre dans une variable de type tableau
        // tous nos utilisateurs pour pouvoir leurs attribués
        // des Post ou des Comment
        $users[] = $user;
        $usersRedac[] = $user;

        // on prépare notre requête pour la transaction
        $manager->persist($user);

        ###
        #
        # INSERTION de 24 utilisateurs en ROLE_USER
        # avec nom et mots de passe "re-tenables"
        #
        ###
        for($i=1;$i<=24;$i++){
            $user = $this->createRandomUser($i);
            // on récupère les utilisateurs pour
            // les post et les comments
            $users[]=$user;
            $manager->persist($user);
        }

        ###
        #
        # INSERTION de 5 utilisateurs en ROLE_REDAC
        # avec nom et mots de passe "re-tenables"
        #
        ###
        for($i=25;$i<=29;$i++){
            $user = $this->createRandomUser($i);
            // on récupère les utilisateurs pour
            // les post et les comments
            $user->setRoles(['ROLE_REDAC']);
            $user->setActivate(true);
            $users[] = $user;
            $usersRedac[] = $user;
            $manager->persist($user);
        }

        ###
        #   POST
        # INSERTION de Post avec leurs users
        #
        ###

        for($i=1;$i<=160;$i++){
            $post = new Post();
            // on prend une clef d'un User
            // créé au-dessus
            $keyUser = array_rand($usersRedac);
            // on ajoute l'utilisateur
            // à ce post
            $post->setUser($usersRedac[$keyUser]);
            $createdDate = $this->faker->dateTimeBetween('-6 month');
            $post->setPostDateCreated($createdDate);
            // Au hasard, on choisit s'il est publié ou non (+-3 sur 4)
            $publish = mt_rand(0,3) < 3;
            $post->setPostPublished($publish);
            if($publish)
                $post->setPostDatePublished($this->faker->dateTimeBetween($createdDate, 'now'));
            
            // création d'un titre entre 2 et 5 mots
            $title = $this->faker->words(mt_rand(2,5),true);
            // utilisation du titre avec le premier mot en majuscule
            $post->setPostTitle(ucfirst($title));

            $post->setPostSlug($this->slugger->slug(strtolower($post->getPostTitle())));

            // création d'un texte entre 3 et 6 paragraphes
            $texte = $this->faker->paragraphs(mt_rand(3,6), true);
            $post->setPostDescription($texte);

            // on va garder les posts
            // pour les Comment, Section et Tag
            $posts[]=$post;

            $manager->persist($post);

        }

        ###
        #   SECTION
        # INSERTION de Section en les liants
        # avec des postes au hasard
        #
        ###
        $categories = ["Actualités & Tendances", "Technologie & Innovation", "Style de Vie & Bien-être", "Entrepreneuriat & Carrière", "Culture & Divertissement", "Voyages & Découvertes"];
        for($i=0;$i<=5;$i++){
            $section = new Section();
            // création d'un titre entre 2 et 5 mots
            $section->setSectionTitle($categories[$i]);
            $section->setSectionSlug($this->slugger->slug(strtolower($section->getSectionTitle())));
            // création d'une description de maximum 500 caractères
            // en pseudo français di fr_FR
            $description = $this->faker->realText(mt_rand(150,500));
            $section->setSectionDescription($description);

            // On va mettre dans une variable le nombre total d'articles
            $nbArticles = count($posts);
            // on récupère un tableau d'id au hasard (on commence
            // à car si on obtient un seul id, c'est un int et pas un array
            $articleID = array_rand($posts, mt_rand(2,40));

            // Attribution des articles
            // à la section en cours
            foreach($articleID as $id){
                // entre 2 et 40 articles
                $section->addPost($posts[$id]);
            }

            $manager->persist($section);
        }

        ###
        #   COMMENT
        # INSERTION de Comment en les liants
        # avec des Post au hasard et des User
        #
        ###
        // on choisit le nombre de commentaires entre 250 et 350
        $commentNB = mt_rand(250,350);
        for($i=1;$i<=$commentNB;$i++){

            $comment = new Comment();
            // on prend une clef d'un User
            // créé au-dessus au hasard, envoie l'id en int
            $keyUser = array_rand($users);
            // on ajoute l'utilisateur
            // à ce commentaire
            $comment->setUser($users[$keyUser]);
            // on prend une clef d'un Post
            // créé au-dessus au hasard
            $keyPost = array_rand($posts);
            // on ajoute l'article
            // de ce commentaire
            $comment->setPost($posts[$keyPost]);
            // écrit entre 1 et 48 heures
            $hours = mt_rand(1,48);
            $comment->setCommentDateCreated(new \dateTime('now - ' . $hours . ' hours'));
            // entre 150 et 1000 caractères
            $comment->setCommentMessage($this->faker->realText(mt_rand(150,1000)));
            // Au hasard, on choisit s'il est publié ou non (+-3 sur 4)
            $publish = mt_rand(0,3) <3;
            $comment->setCommentPublished($publish);

            $manager->persist($comment);
        }

        ###
        #   Tag
        # INSERTION de 45 Tag en les liants
        # avec des Post au hasard
        #
        ###
        for($i=1;$i<=45;$i++){
            $tag = new Tag();
            # création d'un slug par Faker
            $tag->setTagName($this->faker->slug(3));
            # on compte le nombre d'articles
            $nbArticles = count($posts);
            # on en prend 1/5
            $PostNB = (int) round($nbArticles/5);
            # On en choisit au hasard avec maximum 20 tags ($nbArticles/5) = 100/5
            # On choisit 2 articles minimum au hasard sinon on récupère un int
            # et non pas un array
            $articleID = array_rand($posts, mt_rand(2,$PostNB));
            foreach($articleID as $id){
                // on ajoute l'article au tag
                $tag->addPost($posts[$id]);
            }
            $manager->persist($tag);
        }

        // validation de la transaction
        $manager->flush();
    }
}