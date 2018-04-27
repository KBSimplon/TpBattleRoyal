<?php
// On enregistre notre autoload
function chargerClasse($classname)
{
	require $classname.'.php';
}

spl_autoload_register('chargerClasse');

session_start(); // On appelle session_start() APRES avoir enregistré l'autoload.

if (isset($_GET['deconnexion']))
{
	session_destroy();
	header('Location: .');
	exit();
}

if (isset($_SESSION['perso'])) // Si la session perso existe, on restaure l'objet.
{
	$perso = $_SESSION['perso'];
}

$db = new PDO('mysql:host=localhost;dbname=', '', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING); // On émet une alerte à chaque fois qu'une requête a échoué.

$manager = new PersonnagesManager($db);

if (isset($_POST['creer']) && isset($_POST['nom'])) // Si on a voulu créer un personnage.
{
	$perso = new Personnage(['nom' => $_POST['nom']]); // On crée un nouveau personnage.
	if (!$perso->nomValide())
	{
		$message = 'Le nom choisi est invalide.';
		unset($perso);
	}
	elseif ($manager->exists($perso->nom()))
	{
		$message = 'Le nom du personnage est déjà pris.';
		unset($perso);
	}
	else
	{
		$manager->add($perso);
	}
}

elseif (isset($_POST['utiliser']) && isset($_POST['nom'])) // Si on a voulu utiliser un personnage.
{
	if ($manager->exists($_POST['nom'])) // Si celui-ci existe.
	{
		$perso = $manager->get($_POST['nom']);
	}
	else
	{
		$message = 'Ce personnage n\'existe pas !'; // Si il n'existe pas, on affichera ce message.
	}
}

elseif (isset($_GET['frapper'])) // Si on a cliqué sur un personnage pour le frapper.
{
	if (!isset($perso))
	{
		$message = 'Merci de créer un personnage ou de vous identifier.';
	}

	else
	{
		if (!$manager->exists((int) $_GET['frapper']))
		{
			$message = 'Le personnage que vous voulez frapper n\'existe pas !';
		}

		else
		{
			$persoAFrapper = $manager->get((int) $_GET['frapper']);

			$retour = $perso->frapper($persoAFrapper); // On stocke dans $retour les éventuelles erreurs ou messages que renvoie la méthode frapper.

			switch ($retour)
			{
				case Personnage::CEST_MOI :
				$message = 'Mais ... pourquoi voulez-vous vous frapper ???';
				break;

				case Personnage::PERSONNAGE_FRAPPE :
				$message = 'Le personnage a bien été frappé !';

				$manager->update($perso);
				$manager->update($persoAFrapper);

				break;

				case Personnage::PERSONNAGE_TUE :
				$message = 'Vous avez tué ce personnage !';

				$manager->update($perso);
				$manager->delete($persoAFrapper);

				break;
			}
		}
	}
}
?>




<!DOCTYPE html>
<html>
<head>
	<title>Battle Royal !</title>
	<meta charset="utf-8">
</head>
<body style="background-image: url(https://wallpaperscraft.com/image/fight_soldiers_skeletons_3304_2560x1600.jpg); background-size: 100%;">
	<p>Nombre de personnages créés : <?= $manager->count() ?></p>
<?php
	if (isset($message)) // On a un message à afficher ?
	{
		echo '<p>', $message, '</p>'; // Si oui, on l'affiche.
	}

	if (isset($perso)) // Si on utilise un personnage (nouveau ou pas).
	{
	?>
		<p><a href="?deconnexion=1">Déconnexion</a></p>

		<fieldset>
			<legend>Mes informations</legend>
			<p>
				Nom : <?= htmlspecialchars($perso->nom()) ?></br>
				Dégâts : <?= $perso->degats() ?>
			</p>
		</fieldset>	
	}		<legend>Qui frapper ?</legend>
			<p>
<?php
$persos = $manager->getList($perso->nom());

if (empty($persos))
{
	echo 'Personne à frapper !';
}
else
{
	foreach ($persos as $unPerso)
		echo '<a href="?frapper=', $unPerso->id(), '">', htmlspecialchars($unPerso->nom()), '</a> (dégâts : ', $unPerso->degats(), ')</br>';
}	
?> 
			</p>
		</fieldset>
<?php
}
else
{
?>
	<form action="" method="post">
		
		<p>
			Nom : <input type="text" name="nom" maxlenght="50" />
			<input type="submit" value="Créer ce personnage" name="creer" />
			<input type="submit" value="Utiliser ce personnage" name="utiliser" />
		</p>
	</form>
<?php
}
?>
</body>
</html>
<?php
if (isset($perso)) // Si on a créé un personnage, on le stocke dans une variable session afin d'économiser une requête SQL.
{
	$_SESSION['perso'] = $perso;
}


// <?php
// CLASSE :
class Personnage
{
	// PROPRIETES :
	private $_id,
			$_degats,
			$_nom;

	const CEST_MOI = 1; // Constante renvoyée par la méthode 'frapper' su on se frappe soi-même.
	const PERSONNAGE_TUE = 2;// Constante renvoyée par la méthode 'frapper' si on a tué le personnage en le frappant.
	const PERSONNAGE_FRAPPE = 3; // Constante renvoyée par la méthode 'frapper' si on a bien frappé le personnage.

	public function nomValide()
	{
		return !empty($this->_nom);
	}

	// CONSTRUCTEUR :
	public function __construct(array $donnees)
	{
		$this->hydrate($donnees);
	}

	// METHODE 1 :
	public function frapper(Personnage $perso)
	{
	// Avant tout : vérifier qu'on ne se frappe pas soi-même.
	// Si c'est le cas, on stoppe tout en renvoyant une valeur signifiant que le personnage ciblé est le personnage qui attaque.

	// On indique au personnage frappé qu'il doit recevoir des dégats.

		if ($perso->id() == $this->_id)
		{
			return self::CEST_MOI;
		}

		// On indique au personnage qu'il doit recevoir des dégats.
		// Puis on retourne la valeur renvoyée par la méthode : self::PERSONNAGE_TUE ou self::PERSONNAGE_FRAPPE
		return $perso->recevoirDegats();
	}

	// HYDRATE :
	public function hydrate(array $donnees)
	{
		foreach ($donnees as $key => $value)
		{
			$method = 'set'.ucfirst($key);

			if (method_exists($this, $method))
			{
				$this->$method($value);
			}
		}
	}
	
	// METHODE 2 :
	public function recevoirDegats()
	{
	// On augmente de 5 les dégats.

	// Si on a 100 de dégats ou plus, la méthode renverra une valeur signifiant que le personnage a été tué.

	// Sinon, elle renverra une valeur sif=gnifiant que le personnage a bien été frappé.

		$this->_degats += 5;

	// Si on a 100 de degats ou plus, on dit que le personnage a été tué.
		if ($this->_degats >= 100)
		{
			return self::PERSONNAGE_TUE;
		}

		// Sinon, on se contente de dire que le personnage a bien été frappé.
		return self::PERSONNAGE_FRAPPE;
	}


	// GETTERS //


	public function degats()
	{
		return $this->_degats;
	}

	public function id()
	{
		return $this->_id;
	}

	public function nom()
	{
		return $this->_nom;
	}

	public function setDegats($degats)
	{
		$degats = (int) $degats;

		if ($degats >= 0 && $degats <= 100)
		{
			$this->_degats = $degats;
		}
	}

	public function setId($id)
	{
		$id = (int) $id;

		if ($id > 0)
		{
			$this->_id = $id;
		}
	}

	public function setNom($nom)
	{
		if (is_string($nom))
		{
			$this->_nom = $nom;
		}
	}
}

//////////////////////////////////////////////////////////////////////////

class PersonnagesManager
{
	private $_db; // Instance de PDO

	public function __construct($db)
	{
		$this->setDb($db);
	}

	public function add(Personnage $perso)
	{
		// Préparation de la requête d'insertion.
		// Assignation des valeurs pour le nom du personnage.
		// Execution de la requête.

		// Hydratation du personnage passé en paramètre avec assignation de son identifiant et des dégats initiaux (= 0).

		$q = $this->_db->prepare('INSERT INTO personnages(nom) VALUES(:nom)');
		$q->bindValue(':nom', $perso->nom());
		$q->execute();

		$perso->hydrate([
			'id' => $this->_db->lastInsertId(),
			'degats' => 0,
		]);
	}

	public function count()
	{
		// Execute une requête COUNT() et retourne le nombre de resultats retourné.

		return $this->_db->query('SELECT COUNT(*) FROM personnages')->fetchColumn();
	}

	public function delete(Personnage $perso)
	{
		// Execute une requête de type DELETE.

		$this->_db->exec('DELETE FROM personnages WHERE id = '.$perso->id());
	}

	public function exists($info)
	{
		// Si le paramètre est un entier, c'est qu'on a fourni un identifiant.
		// On execute alors une requête COUNT() avec une clause WHERE, et on retourne un boolean.

		// Sinon c'est qu'on a passé un nom.
		// Execution d'une requête COUNT() avec une clause WHERE, et retourne un booléan.

		if (is_int($info)) // On veut voir si tel personnage ayant pour id $info existe.
		{
			return (bool) $this->_db->query('SELECT COUNT(*) FROM personnages WHERE id = '.$info)->fetchColumn();
		}

		// Sinon, c'est qu'on veut vérifier que le nom existe ou pas.

		$q = $this->_db->prepare('SELECT COUNT(*) FROM personnages WHERE nom = :nom');
		$q->execute([':nom' => $info]);

		return (bool) $q->fetchColumn();
	}	

	public function get($info)
	{
		// Si le paramètre est un entier, on veut récupérer le personnage avec son identifiant.
		// Execute une requête de type SELECT avec une clause WHERE, et retourne un objet Personnage.

		// Sinon, on veut récupérer le personnage avec son nom.
		// Execute une requête de type SELECT avec une clause WHERE, et retourne un objet Personnage.

		if (is_int($info))
		{
			$q = $this->_db->query('SELECT id, nom, degats FROM personnages WHERE id = '.$info);
			$donnees = $q->fetch(PDO::FETCH_ASSOC);

			return new Personnage($donnees);
		}
		else
		{
			$q = $this->_db->prepare('SELECT id, nom, degats FROM personnages WHERE nom = :nom');
			$q->execute([':nom' => $info]);

			return new Personnage($q->fetch(PDO::FETCH_ASSOC));
		}
	}

	public function getList($nom)
	{
		// Retourne la liste des personnages dont le nom n'est pas $nom.
		// Le résultat sera un tableau d'instances de Personnages.

		$persos = [];

		$q = $this->_db->prepare('SELECT id, nom, degats FROM personnages WHERE nom <> :nom ORDER BY nom');
		$q->execute([':nom' => $nom]);

		while ($donnees = $q->fetch(PDO::FETCH_ASSOC))
			{
				$persos[] = new Personnage($donnees);
			}

			return $persos;
	}

	public function update(Personnage $perso)
	{
		// Prépare une requête de type UPDATE.
		// Assignation des valeurs à la requête.
		// Executin de la requête.

		$q = $this->_db->prepare('UPDATE personnages SET degats = :degats WHERE id = :id');

		$q->bindValue(':degats', $perso->degats(), PDO::PARAM_INT);
		$q->bindValue(':id', $perso->id(), PDO::PARAM_INT);

		$q->execute();
	}

	public function setDb(PDO $db)
	{
		$this->_db = $db;
	}
}
?>