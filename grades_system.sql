-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 12:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `grades_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `row_hmac` varchar(64) DEFAULT NULL COMMENT 'HMAC-SHA256 of (log_id|user_id|action|action_time) for tamper detection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `action_time`, `row_hmac`) VALUES
(1, 2, 'User logged in', '2026-03-24 03:54:13', '02fa95b348dddb68e5aa65c77686d1da673e17e05fb51462812c6e727535a7b3'),
(2, 2, 'Updated Prelim grade components for subject 2', '2026-03-24 04:23:20', '7326f3a70762445c5a07679203f5f3e03e9b5e2ebdbe69f470179db572521cec'),
(3, 2, 'Updated Prelim grade components for subject 2', '2026-03-24 04:31:06', '05b70e2da34885199cd56ddfc51fa7a2292152dbc65b13d77ff36d4eaca9f03e'),
(4, 2, 'Updated Prelim grade components for subject 2', '2026-03-24 04:31:41', '10dd2788c7ee9f78b3ced3ad2f26cbdc3b085b090fb522f9889befbb67807d6f'),
(5, 11, 'User logged in', '2026-03-24 04:33:18', '558fe7e159c6f810ac890eb2a2f64bcad1fb50d151b07945f434518da9e6c187'),
(6, 11, 'Created user: Kurt Magallanes (kurt.student@gmail.com)', '2026-03-24 04:34:15', 'a37e3efc44f5c288ea26eb5fa72ec4bc8e75fd9bf10886e4412418e3dc2e9756'),
(7, 13, 'User logged in', '2026-03-24 04:34:48', '225c7f807d060f3aed91815fc7ce5833a45b401f22dc7909d83764c2c6168b99'),
(8, 13, 'Submitted enrollment request for subject 2', '2026-03-24 04:35:00', '704f801b0e91201f787d703779b5432219b1f21a60369f60f0bd62916c5b2c3a'),
(9, 8, 'User logged in', '2026-03-24 04:35:22', '2299d7c90dc5a7343582bf2ebf00f25712f2aff64b6e11f1cccc0d158ca7ae87'),
(10, 8, 'Submitted enrollment request for subject 2', '2026-03-24 04:35:26', 'e8269bfbbeb1071cda986688af36c9ec929e1ff25cdb6ef8f7463fc3e32b9490'),
(11, 10, 'User logged in', '2026-03-24 04:35:38', '015e07dd6c7634fcd39c5d81aff03c9677b48cc3ed6530cba9773ddedce1d840'),
(12, 10, 'Submitted enrollment request for subject 2', '2026-03-24 04:35:42', 'a1115c2b8641cd570fc69de644fe22b988b0c07e19f66e18c0d3db09520b1eea'),
(13, 9, 'User logged in', '2026-03-24 04:35:55', '9a9e39f8de9709e5dc187c47628f27032d49a6d279cb4e9a23a3a5c8113b0087'),
(14, 9, 'Submitted enrollment request for subject 2', '2026-03-24 04:35:59', '1f9f31972ab45955fc0b665ab339c8bbec6c7954e61604b897a42091bd2d0d8e'),
(15, 12, 'User logged in', '2026-03-24 04:36:18', 'f34f859f1413868a5429e239974cd93190de648f48e3bc6e4034e646795eb289'),
(16, 12, 'Submitted enrollment request for subject 2', '2026-03-24 04:36:22', 'c6d592da06c39a3515895bfd33e9efd34bb696def919b7cec5fb0cc0f9512bde'),
(17, 7, 'User logged in', '2026-03-24 04:36:41', '3901798bb9f29ee45f61a6ebf6243402da8255dfabd1dfda237f9d188b0a2598'),
(18, 7, 'Viewed pending enrollment requests', '2026-03-24 04:36:45', '47fb95265acb8828e004f602f175d8d059bfe4f073b395b428c39cb2284298c9'),
(19, 7, 'Approved enrollment request 5', '2026-03-24 04:36:50', '1ed0a744ad3114729fca00871d68a703db94c34e48b6f3020347e029fe0ca68b'),
(20, 7, 'Viewed pending enrollment requests', '2026-03-24 04:36:50', '2400e9fc16f16f2b53f665bd4b902bdc9ec67a19b1f48fda1734f6168898c7f5'),
(21, 7, 'Approved enrollment request 4', '2026-03-24 04:36:57', '8805b5f889570c575ad95094c73b0de38580228a21534b7b15230903e6f27426'),
(22, 7, 'Viewed pending enrollment requests', '2026-03-24 04:36:57', 'dbcc4c6799036d7ee26d94808756b516295dcb86a496725557c95a8cf34ae8d4'),
(23, 7, 'Approved enrollment request 3', '2026-03-24 04:37:00', 'e7cb50acef947629f735ff67b79e10eef40d3f6e59c4464f08e97f9148747899'),
(24, 7, 'Viewed pending enrollment requests', '2026-03-24 04:37:00', '313e6b7aca968bf4509c3f10fe354fec68fcd6ba70c742f906fb82ac9e7d175a'),
(25, 7, 'Approved enrollment request 2', '2026-03-24 04:37:03', '22c1a70c09da95aee56fb84fd8c5e43b66adf3bdefdc239b8cd17c071bb73dd7'),
(26, 7, 'Viewed pending enrollment requests', '2026-03-24 04:37:03', '0174ff3c6fdb908f3ff8f3df639e71e43b399cab002c44c0944f473edf5e893a'),
(27, 7, 'Approved enrollment request 1', '2026-03-24 04:37:05', 'c28e7183002dd946a139019c1717f5763e74d2bf03e623d4019bae9dab346b36'),
(28, 7, 'Viewed pending enrollment requests', '2026-03-24 04:37:05', 'bc6b22321a21f0b31ba2bd4d9ef20db2667abe598ab1a88037ca256dd7f6098f'),
(29, 7, 'Viewed pending correction requests', '2026-03-24 04:37:12', '302a37347c07373a26cb9a68de69adc1d63592f27f6d1b1bd9aa9a30dbddfa78'),
(30, 2, 'User logged in', '2026-03-24 04:37:31', '0ec6b88cfe7c497f92aa12b012bf5d8babe7c66918d85f87c642fe0b77bc80a1'),
(31, 2, 'Encoded Prelim grade for student 12 in subject 2 (3rd Year - 2nd Semester): 71.75% (5)', '2026-03-24 05:06:07', '22701959df824d8296525c89a7908b3e58f84723c212feac6054988f6e5a6667'),
(32, 2, 'Encoded Prelim grade for student 10 in subject 2 (3rd Year - 2nd Semester): 73.75% (5)', '2026-03-24 05:06:08', '1ba43c39f604ab86beeb3c8fb0012f69a23ac33d060e51d391ca4e12241452e0'),
(33, 2, 'Encoded Prelim grade for student 13 in subject 2 (3rd Year - 2nd Semester): 73.75% (5)', '2026-03-24 05:06:09', '697d1ba89dd6d07d2e1a4c69000de5b8c2f71958e22894fd91e4d45ccdbc321d'),
(34, 2, 'Encoded Prelim grade for student 9 in subject 2 (3rd Year - 2nd Semester): 73.75% (5)', '2026-03-24 05:06:09', '2acf30c87b89d5fa8652788634b0526b71b7a8c510d6cc1614b6862a69177d14'),
(35, 2, 'Encoded Prelim grade for student 8 in subject 2 (3rd Year - 2nd Semester): 81.25% (2.5)', '2026-03-24 05:06:10', '76ffb954e5283d0e21104faab9fda82eac6cd053d73777b2578066efb276280e'),
(36, 2, 'Encoded Prelim grade for student 12 in subject 2 (3rd Year - 2nd Semester): 75.75% (3)', '2026-03-24 05:09:35', '6616b9910967182615ccce08ff09bf63e2aeed8296956ccd9d34cc8138a1f152'),
(37, 2, 'Encoded Prelim grade for student 10 in subject 2 (3rd Year - 2nd Semester): 77.75% (2.75)', '2026-03-24 05:09:36', '5513ebd6ada0e5d74d0aaa2fba1be25789782f58bc634a43d3780423073b6dec'),
(38, 2, 'Encoded Prelim grade for student 13 in subject 2 (3rd Year - 2nd Semester): 77.75% (2.75)', '2026-03-24 05:09:36', '8c2fdadd0e12866402894af425b4afc1447fe3c4ebfe49cdf83cf90b7c32c748'),
(39, 2, 'Encoded Prelim grade for student 9 in subject 2 (3rd Year - 2nd Semester): 77.75% (2.75)', '2026-03-24 05:09:37', '26ecc4f7b0c571b841badbec8167bef0aba944df6cb1ee35f56a69421d1e0ccc'),
(40, 2, 'Encoded Prelim grade for student 8 in subject 2 (3rd Year - 2nd Semester): 81.25% (2.5)', '2026-03-24 05:09:37', '61516b1b0fefd248255b19f475ebea7c32d266c4f3bdc45946348064e74946ef'),
(41, 2, 'Encoded Prelim grade for student 12 in subject 2 (3rd Year - 2nd Semester): 75.75% (3)', '2026-03-24 05:21:41', '5c58f3c0785d9e379069c431ae154bfefd685108a9e5571226a5fce051e8c24e'),
(42, 2, 'Encoded Prelim grade for student 10 in subject 2 (3rd Year - 2nd Semester): 80.15% (2.5)', '2026-03-24 05:21:44', '96648c1af901dcf4706a2eccee2a0ba5f33b3e59ad40e93576d4d6d233e04332'),
(43, 2, 'Encoded Prelim grade for student 13 in subject 2 (3rd Year - 2nd Semester): 77.75% (2.75)', '2026-03-24 05:21:45', 'f34c6c36011ae8f19593b531dcec6b46cd7dcae395feebfbf33becd40a8a5e96'),
(44, 2, 'Encoded Prelim grade for student 9 in subject 2 (3rd Year - 2nd Semester): 77.75% (2.75)', '2026-03-24 05:21:47', '005f701cef8f2f46afda60d868440c682b007a1904be12a0fbda3bafa651f95e'),
(45, 2, 'Encoded Prelim grade for student 8 in subject 2 (3rd Year - 2nd Semester): 81.25% (2.5)', '2026-03-24 05:21:49', '18845600a6068c6a1f264a60ebbac3b08f0f4e1f497351ceb7efd179d51262c1'),
(46, 7, 'User logged in', '2026-03-24 05:23:13', 'ebb1b644f9c0fc7dba32f8ebc168b65e20fddd08741d5c74b1b30477e6acd9fc'),
(47, 7, 'Grade 1 approved by registrar', '2026-03-24 05:23:30', '173339eb5a521926c5fc7f9324a3b4de3545081fb665d921f875bcaf8d217641'),
(48, 7, 'Grade 3 approved by registrar', '2026-03-24 05:23:33', '1bd5601ac3f939e7f964711275b155dc13daa0c578f5c1c662b02989ba0c1464'),
(49, 7, 'Grade 4 approved by registrar', '2026-03-24 05:23:36', 'bfb863e1c7ba3affb09385f5da0ef81a5276683fd6bd28e8d067cfb6cfd31dc3'),
(50, 7, 'Grade 5 approved by registrar', '2026-03-24 05:23:38', '1b2d78fe3aed03337cec4c3e5162cd895c76d87ff4df4c022800f69066eaaada'),
(51, 7, 'Grade 6 approved by registrar', '2026-03-24 05:23:41', 'e630ba48deff1b4610117f31a4c32482cee3b14f9b39f79148450a78738ee172'),
(52, 2, 'User logged in', '2026-03-24 05:24:03', '35f8dc90c9f3f1e0e85633eb9c589902147b4a838d7755adca575d90d7c4ca69'),
(53, 7, 'User logged in', '2026-03-24 05:25:47', 'a08bb252b6427506422f195e02a370f66148a9531ad9d6b4bed345b2e3bcd6be'),
(54, 7, 'Viewed pending enrollment requests', '2026-03-24 05:25:55', '9656646086e7669b85995f4097fe1bfe8b99fd175cd49d8964f73aeaa8dc9464'),
(55, 7, 'Viewed pending correction requests', '2026-03-24 05:25:58', '02adafcb3cc31dd125ff62e2d484b9ae071d70eabc73b1e2fb24f1389fa0645b'),
(56, 2, 'Requested correction for grade ID 1', '2026-03-24 05:28:24', 'b4e9b659b9aeb058af6269d3911d3436068e8b7e0c6f32f83612447af5350612'),
(57, 7, 'Viewed pending correction requests', '2026-03-24 05:28:28', 'c298ac540ded955e3aa4da447c7f9f878f4c62b95aed6637327ee70ac107583d'),
(58, 7, 'Approved correction request ID 1 for grade ID 1', '2026-03-24 05:28:42', '36aa0ef009f52477b07ed3306263cf841537cff637a198a97ca6c9259c2d01c2'),
(59, 7, 'Viewed pending correction requests', '2026-03-24 05:28:42', '804f1e8abdec4cfae29761643f4e1380efcab2a80287a04b3d22f5db4f763da4'),
(60, 2, 'Encoded Prelim grade for student 12 in subject 2 (3rd Year - 2nd Semester): 76.75% (3)', '2026-03-24 05:29:12', 'cdd17ecce5bc870fa9023cdc83ec78edc2eb55e528859417a51e539f039ad5bd'),
(61, 7, 'Grade 1 approved by registrar', '2026-03-24 05:29:26', 'e4cf953a1f829759777946c01bdb234810a88759c431c73bb2bf21fd20099914'),
(62, 2, 'Submitted semestral grade for student 12 in subject 2 (3rd Year - 2nd Semester): Final=23.025 (5)', '2026-03-24 05:30:52', '79026c13f704a7edf5391705167aa31f9f27ab3eba910deed3dd31beddc9a263'),
(63, 2, 'Submitted semestral grade for student 10 in subject 2 (3rd Year - 2nd Semester): Final=24.045 (5)', '2026-03-24 05:30:53', '7dd60302cf9eab860272a2d0255647c15613cae76de6f7c2a1346a3fe1c32620'),
(64, 2, 'Submitted semestral grade for student 13 in subject 2 (3rd Year - 2nd Semester): Final=23.325 (5)', '2026-03-24 05:30:53', '154b656c73aa872d28904466eb4e3bbf42d7a37dbb6be40ce0578de51d4bd140'),
(65, 2, 'Submitted semestral grade for student 9 in subject 2 (3rd Year - 2nd Semester): Final=23.325 (5)', '2026-03-24 05:30:53', '07433306c5dd0ebcd2468c485c6a53484c9e94dfea15e6938f8448123c792915'),
(66, 2, 'Submitted semestral grade for student 8 in subject 2 (3rd Year - 2nd Semester): Final=24.375 (5)', '2026-03-24 05:30:53', '52f6014d92c2ebc8afe0b09485e8423272b45a3ee497cbafb6005bebf843d958'),
(67, 7, 'Viewed pending enrollment requests', '2026-03-24 05:31:06', 'eec6ba2f485d204ff48ad59d266058bd081a9d948f831a4ae913f828066d33f8'),
(68, 7, 'Viewed pending correction requests', '2026-03-24 05:31:09', 'b530a98551f528292acde3470df8116bad7fc0c43ec6276469cdf2d3bb3882a2'),
(69, 7, 'Viewed pending correction requests', '2026-03-24 05:31:28', '14fd54f6e8bdc95cb7b63f66d9766b211ebd684aa40bd2a0ecca7585e16c7a82'),
(70, 7, 'Viewed pending enrollment requests', '2026-03-24 05:44:13', 'f62532be8c21217be8d23d0fb9a0e0061fcc415856b19064e19015d93042eebe'),
(71, 7, 'Viewed pending correction requests', '2026-03-24 05:44:16', '7c26a699afba033d0843a5c476676f6f178f7b6a66a7b201be602b91c434efb9'),
(72, 7, 'Viewed pending enrollment requests', '2026-03-24 05:44:19', '826378fbbd2cdd90b7f45c5286da7caa70614ada922743a0bc6aac7db6ef9d98'),
(73, 7, 'Viewed pending correction requests', '2026-03-24 05:44:22', '4a967689bb9ec1ad88f8dad3f1534a14095a247a622a3f5b12d579aba2032322'),
(74, 7, 'Viewed pending enrollment requests', '2026-03-24 05:44:24', '15433c974dcc825686ac4170cf9900edb2c53fcdffcdf5e27f3d387ec9e3a66c'),
(75, 7, 'Viewed pending enrollment requests', '2026-03-24 05:44:32', 'fce13e7ae9d70a5dc12317370a40675cd48983109a4326dd427d3bb672473bb8'),
(76, 7, 'Viewed pending correction requests', '2026-03-24 05:44:35', 'bbb52a9acf1862125113fe868dd58e755ccd23902d02a146f731a0de7dd2dbb8'),
(77, 7, 'Viewed pending correction requests', '2026-03-24 06:04:14', '6bcace7866c434c43181e5c8aeed5298d4b3f505800c206df50e0efc88a82525'),
(78, 7, 'Viewed pending enrollment requests', '2026-03-24 06:04:15', '3a501857a686d2cba8417e69322da5e5f8a82871277a7167a918683ff6345b8e'),
(79, 7, 'Viewed pending correction requests', '2026-03-24 06:04:18', '8635a0203e01a60559b4090f1d634f97fccd452cace13e5ce91c9ef9bad9fee9'),
(80, 7, 'Semestral grade ID 1 approved by registrar', '2026-03-24 06:19:17', 'cec4f123207db7ded9f64e443cbdcedfe8f6b87a3535dd91bebf78588a8fd8ab'),
(81, 7, 'Semestral grade ID 2 approved by registrar', '2026-03-24 06:19:24', '4afe42ebdaabcb083853608ccd034d49a1941a947c61ae5492433a3e9566857c'),
(82, 7, 'Semestral grade ID 3 approved by registrar', '2026-03-24 06:20:01', '1cbafd96ccc4f7e2d114b1e2c9cee96e0b6e509db07dd8f983ed0f73892a3156'),
(83, 7, 'Semestral grade ID 4 approved by registrar', '2026-03-24 06:20:58', 'e66c0aa4d37efea5a483aa275db77c79e2c0c1f59ddf14a5c81d5e5d8cdd2b74'),
(84, 7, 'Semestral grade ID 5 approved by registrar', '2026-03-24 06:21:58', 'df043dca9c49c363b104c18a489e95633cb61a90865c5465a1ddd161b8f5a186'),
(85, 7, 'Viewed pending enrollment requests', '2026-03-24 06:36:41', 'b4511f3d5a632d83f73cf4b782ee2db85ea6b16b788611b7421e2310553ee6fd'),
(86, 7, 'Viewed pending correction requests', '2026-03-24 06:36:43', 'c2c8464a18201a3bc95975e9c826332edafe82ad65f8d7e3d7372b481f2b44d3'),
(87, 7, 'Viewed pending enrollment requests', '2026-03-24 06:36:45', '382f50daf06ad558077cdec66e067a4bf166ac631799c7994bd7f6658c7df3cb'),
(88, 7, 'Viewed pending correction requests', '2026-03-24 06:36:49', '629946236d6f9989f424ba80e0e38f13ed5a26c16ff40465522dd29e363cb534'),
(89, 7, 'Viewed pending enrollment requests', '2026-03-24 06:36:52', 'a070724b7974cd1235ad43d848494005e40e0d248f450d807c12b498322fad1d'),
(90, 7, 'Viewed pending correction requests', '2026-03-24 06:37:33', 'f46265894fc5a2854dd1559f3373502b3317f6bb0aefc4b7f27e957d296ba4f6'),
(91, 7, 'Viewed pending correction requests', '2026-03-24 06:51:39', '7abd8d3be7617a6b69bd8d4901faa8d3d99668647edaf768e3fed7e7f60dd3b1'),
(92, 7, 'Viewed pending correction requests', '2026-03-24 07:11:14', '54378a4f25dc1cfca8778345705f6f3f559e56acd612f73b4f9d3340dcf415d8'),
(93, 7, 'Viewed pending enrollment requests', '2026-03-24 07:11:19', 'f76f22b785ae6f94a5fd59bafd2166f5deac4e711f3eed57cf096c951330aedf'),
(94, 7, 'Viewed pending enrollment requests', '2026-03-24 07:14:03', '8e6971d4e318a0986d3a2dadfc053c5fb46c458868d2325664269b7385687694'),
(95, 7, 'Viewed pending correction requests', '2026-03-24 07:14:21', '136f45766a5fcc834eb8197f35c574fdbdc1d3fdd7bc49d68f49edf4f5a6966b'),
(96, 7, 'Viewed pending enrollment requests', '2026-03-24 07:21:11', 'b3e35b1c9c01eded260113ee5eae446887abd9f5bc1fd905f0aba30c4d59f4fa'),
(97, 7, 'Viewed pending enrollment requests', '2026-03-24 07:41:34', '343dcea00a14f6b6b2517ab158783797f37302823a849d96bd4590234442ffae'),
(98, 7, 'Viewed pending enrollment requests', '2026-03-24 07:41:59', '402ca219a1d43e1562cc4c2b7518a9217f427204399a6d54c5ec3fb5b01f7771'),
(99, 7, 'Viewed pending enrollment requests', '2026-03-24 07:42:04', '1a8d08b7c790432515e3a5940ae242dfae6129748c67490552ef89b3400c2819'),
(100, 7, 'Viewed pending enrollment requests', '2026-03-24 07:42:14', '9860ec39306a022432ff2ff099e8be6ce9fb740e1a9532cd182433eb3eb465e4'),
(101, 7, 'Viewed pending enrollment requests', '2026-03-24 07:45:21', 'c7f629ef33955227f05e9821dfa0e763d4f0a4f8a7aeeecf689cd208aea19b5f'),
(102, 7, 'Viewed pending enrollment requests', '2026-03-24 07:45:24', '1e6e3b3aeb556f46caba9aaa0411fc2d32347ad5b4cdeaa945c237a0edc72cbc'),
(103, 7, 'Viewed pending enrollment requests', '2026-03-24 07:47:52', '4601ebf263dd7a038670c292741c6fecb4e8f7a155014339563f3d81e9e7186e'),
(104, 7, 'User logged in', '2026-03-24 07:48:53', '882bcc535036b1cd00f80eae631e8f71e07ecdff0eae7d677c07766aa8c28642'),
(105, 7, 'Viewed pending enrollment requests', '2026-03-24 07:51:36', '88ca21457ed45a2d53c914983a6feda9af7aafe998d0ad9ca13d56d8ce39b10e'),
(106, 7, 'Viewed pending correction requests', '2026-03-24 07:51:38', '42a3a7d970c9c142c709bf4926170f852703126f2a838bad2b01f85a52d49ad4'),
(107, 7, 'Viewed pending correction requests', '2026-03-24 07:51:40', 'fde6cb698bca01d4238f60fd39f3e4ee7a7b537200c80561e4a3f8ebdfe1e174'),
(108, 7, 'Viewed pending enrollment requests', '2026-03-24 07:54:55', '4d009a5aad91adb630abcdc2b46bbcd48d005eeb7f129d8663fcd9e301bab404'),
(109, 7, 'Viewed pending enrollment requests', '2026-03-24 07:55:04', '1b02da96a3b304f80b9faaad941d6f9786a70c58bc9790b68f33d6eb8ad47215'),
(110, 7, 'Viewed pending enrollment requests', '2026-03-24 07:55:21', '12663648423e79143f4f9e05dda605b50651c5589607e587106a09cc9e7e4e14'),
(111, 7, 'Viewed pending enrollment requests', '2026-03-24 07:58:00', '66f8228e323c6b43323e149cbe9e33f9a5185b76273b5b7306b774e0b0bed6cd'),
(112, 7, 'Viewed pending enrollment requests', '2026-03-24 08:29:48', '4f9b45531a96ddb1fbd66ae01b18ddd601049bb8e31aa2be4eb4bf90659c82fe'),
(113, 7, 'Viewed pending enrollment requests', '2026-03-24 08:30:26', 'a357e91dcdd5f2484f6baf466bfcdbbb5763516c3100d1bc6e46fcb4639a424f'),
(114, 7, 'Viewed pending enrollment requests', '2026-03-24 08:30:31', 'cac359402ce93bf6204b4e13efed5d9315008b02bb19ee235f889e7b1f54b119'),
(115, 7, 'Viewed pending enrollment requests', '2026-03-24 08:30:56', 'a4a915383a725a8c69734d264c1da33ac08b75178d3773cd1465e5adf23dc7c7'),
(116, 7, 'Viewed pending enrollment requests', '2026-03-24 08:33:11', '00cc37fc80f1a980a2b0bb73224877ee6c72a37916eedef2b97714ad271217fc'),
(117, 7, 'Viewed pending enrollment requests', '2026-03-24 08:33:17', '4d987e6190b6316ca16d2d5302ee401d06c94cb8ceb03ad2f6df147c9cf72f09'),
(118, 7, 'Viewed pending enrollment requests', '2026-03-24 08:33:24', '82c4cbc8cf985f8fc0ee4790b82922aa62a2790851faa571617b1c6234a93074'),
(119, 7, 'Viewed pending enrollment requests', '2026-03-24 08:34:16', '2956487041fb8c2f6b5240b5fec877bcd2282d121340d50390ad1b861a87e23b'),
(120, 7, 'Viewed pending enrollment requests', '2026-03-24 08:34:20', '6e58de12a0b5dbad6a338f31bb84662d835e20f899016403e36c1138cf97589a'),
(121, 7, 'Viewed pending enrollment requests', '2026-03-24 08:35:00', 'f8d78b9820e967df36b1ff7ed6591d599d651d9e7429d3f2107458153f4bc63c'),
(122, 7, 'Viewed pending enrollment requests', '2026-03-24 08:36:10', 'f5e73f0f05e46037b8cdbe24d0a3301bdf129be8e9989c4a2a7419e11df06f09'),
(123, 7, 'Viewed pending enrollment requests', '2026-03-24 08:41:03', 'a12d85b7ffa9385f6958f43d3d28238ac4fdc9f2ec6da41096351c5c2ba476fe'),
(124, 7, 'Viewed pending enrollment requests', '2026-03-24 08:41:58', '753a3543e0eabfaf62b99457aeb38e783c047688c7a9a535fb4a4adcf7ef6857'),
(125, 7, 'Viewed pending enrollment requests', '2026-03-24 08:42:23', '7915fcae5fa39467dd3b4cb6f64b6ae85254d81654c373bf83b7921ce80b328c'),
(126, 7, 'Viewed pending enrollment requests', '2026-03-24 08:42:38', '369338ed13580b4cdc525283b5066abbb52756905e7519dfcd4750c9f1ba0a84'),
(127, 7, 'Viewed pending enrollment requests', '2026-03-24 08:42:54', '75d2ac302c12c495348616fd3c002507ddc2a4e7de12363b5c37887618e8d71b'),
(128, 7, 'Viewed pending enrollment requests', '2026-03-24 08:43:18', '7a9b73462e830e2dffe8a66c9c61bf461d1af59f25df4c1c8222b16adbd55981'),
(129, 7, 'Viewed pending correction requests', '2026-03-24 08:43:22', '63d8b1edf3b5fca574afd6899a468d97bb4760d7f67771289a4bb959e1c63ce5'),
(130, 7, 'Viewed pending enrollment requests', '2026-03-24 08:43:24', '08aba7f19d3830a3db2c4ff9f484cbdf7a283f82d4544f8e85b164dab758424c'),
(131, 7, 'Viewed pending enrollment requests', '2026-03-24 08:45:24', 'efa7f0defe927035d41b07da5969002a82d577b19b858b2107956f660669f43b'),
(132, 7, 'Viewed pending enrollment requests', '2026-03-24 08:45:43', '12f24bea1803c68ab653e9fff58ecee438759603c9ecea82e7e7521da618a55a'),
(133, 7, 'Viewed pending enrollment requests', '2026-03-24 08:45:48', 'bc07e52111f955f34a5f4bd0d794e612b42e95e35cc939afadc399ebae5f705d'),
(134, 7, 'Viewed pending enrollment requests', '2026-03-24 08:47:25', '7ad4619c730ec32f4d42019b899cd3f21823183f56459edc7c702afd5d9dc5d2'),
(135, 7, 'Viewed pending enrollment requests', '2026-03-24 08:58:07', '820050682181ed17034dfb22ef39862c910141e77243f423dcace96e73c525ab'),
(136, 7, 'Viewed pending enrollment requests', '2026-03-24 08:58:12', '2c62dba5a8591b15e927fd339fec76837f5c6d78d238ceff4dff5c84fe9948d2'),
(137, 7, 'Viewed pending enrollment requests', '2026-03-24 08:58:54', '65cb150073124108b6fa5d168f5073e854fb9f1d3bd6eea7e5e88e89ff4db789'),
(138, 7, 'Viewed pending enrollment requests', '2026-03-24 08:58:58', 'da708e47653a3d00800a41fe0ecec0d632d3a3c059ab75fe48d1c4a4e782d55f'),
(139, 7, 'Viewed pending enrollment requests', '2026-03-24 08:59:39', '58615b4d2e20d0c0da4f91a6669d4d47d736b1bb6c3c47f4ccd5758c46c40a92'),
(140, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:06', '730dcdd97b928d5697972a002f50b0ae79b29dea0b95ba1582a60babccedb12f'),
(141, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:10', 'fed1ad371bceccb42862b7109673271f64153def270092e3d8e77d5c766eedd5'),
(142, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:29', '8d430fda3254e6f344263ea92c20cb1f6fc89897abecf530502af7513a83bb05'),
(143, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:32', '51e482619e0cf91e7c5f3ae93ce63c1755d1335ddf1e67aee5f6227d375032a1'),
(144, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:37', '5cff9f40ccbc7b2ef8887b57e55d3cc11c22826a489d6e15d9c15c6ef945e515'),
(145, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:40', '4137bac32263153fc37129e2dab4e1cd781a70cbe68b824bc566ec3d24701b8b'),
(146, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:43', '89104e2d23530791180c2258d8240cd629c3e0508531cb29c6cf213bf2085f50'),
(147, 7, 'Viewed pending enrollment requests', '2026-03-24 09:00:51', 'e91e2d660cf0edb052ab7f7c9344996aad423790e73b16d614d0de2a749c7e01'),
(148, 7, 'Viewed pending correction requests', '2026-03-24 09:00:55', 'b9f5de5716c39a78048438cd1cda23b82452cb1e85ebb7081f223dc4bcc6a58e'),
(149, 7, 'Viewed pending enrollment requests', '2026-03-24 09:01:02', '2124296654286d133b658f3df1dca4073682637ed9ac7d4f1c741701145c96c2'),
(150, 7, 'Viewed pending enrollment requests', '2026-03-24 09:01:16', 'a7518b59685147a7af1a487478e65e586e849a64a6f3cfb47021b5cbc9ce56d5'),
(151, 7, 'Viewed pending enrollment requests', '2026-03-24 09:01:46', 'cfcd2dd4db2ad001cbbf39dba1f3db761283c93f2d9979cf701e5e789ed69358'),
(152, 7, 'Viewed pending enrollment requests', '2026-03-24 09:02:04', '1b97940fed0d43e73569b4a6f1580053e788a28607b6f39c49b9b886a2205dda'),
(153, 7, 'Viewed pending enrollment requests', '2026-03-24 09:02:10', 'fa5526e62a4907b9088c314c308c85c415bf0a65d5b3494986a8a13b328b37e9'),
(154, 7, 'Viewed pending enrollment requests', '2026-03-24 09:03:03', 'e424a687afa85e3496c3b99a84c11ad2c10b01de9afec4f685849f331b6a3356'),
(155, 7, 'Viewed pending correction requests', '2026-03-24 09:03:04', '7e7ef02964e0feae9584e0d2ebddab8bc7b76bed00304e02846a2f02f33fdd1b'),
(156, 7, 'Viewed pending enrollment requests', '2026-03-24 09:03:07', 'bb0f3252ae4da24603dc0b6962f8e10ca99852a2404e69495c0060b7a0c46618'),
(157, 2, 'User logged in', '2026-03-24 09:04:09', '211773bf70681263d92365214c4533da310e3749d1bcc6c274c5f2da712c14fc'),
(158, 2, 'User logged in', '2026-03-24 09:09:20', '2772157810c90e769aa332828594fb328f7c2405d1cd0c16ca28c624625c4daf'),
(159, 7, 'User logged in', '2026-03-24 09:09:44', '598751572f96855427a22a7434c98b87d94b1097f312ebd1de066e0b341ef40a'),
(160, 7, 'Viewed pending enrollment requests', '2026-03-24 09:09:47', 'aa57c23810292b73a6e874342d200a17518ced8f1b4846313b4557a715ad0987'),
(161, 7, 'Viewed pending enrollment requests', '2026-03-24 09:09:51', '0cf1bc72fe896efeda1be7c3c0877619e2a614d5e402a1e5869976e5946a82d9'),
(162, 7, 'Viewed pending enrollment requests', '2026-03-24 09:09:55', 'fd485ff6b24235849d3f8f15189d7366204c756ff7ba9b2d247685292b312393'),
(163, 11, 'User logged in', '2026-03-24 14:59:25', '46cc2c3de09ac75b62dc457c847f832e7c5491dc7815fb45c76ce4423c9b36a0'),
(164, 11, 'Created user: Mark Pagdilao (mark.student@gmail.com)', '2026-03-24 15:00:25', 'adf00d0290f57c9b8a939cd84a4857914639a664c224960528b56236bd97d962'),
(165, 14, 'User logged in', '2026-03-24 15:00:43', '73f84acfed15d675be627d6c3e6e8f1ed313bc47a6dcabd7bf55d62c1cdbcce5'),
(166, 14, 'Submitted enrollment request for subject 2', '2026-03-24 15:00:48', 'b447830971d7e3f2449454a261e7b355e1d40293d5fa9c91c105fa1bb9572d46'),
(167, 2, 'User logged in', '2026-03-24 15:08:13', '3dfc10d23c77c0e95d0641d242fb0956ea639bf8ac7354e6739f012891212890'),
(168, 7, 'User logged in', '2026-03-24 15:09:02', '494a172a8016c409078bd0baa1fb44a9cfd0dc8a36e791e51daf86762d96e6f7'),
(169, 7, 'Viewed pending enrollment requests', '2026-03-24 15:09:07', '29fe57d1c0983e70cdd382c8d5e6b64b858e1169e3d5f41cc51385f3c6beab17'),
(170, 7, 'Viewed pending enrollment requests', '2026-03-24 15:09:12', 'e28138be2107897cc36f1999bc12353466e5f40c929202cfb1330254e9d9af4c'),
(171, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:16', 'f0c9679e078d08146dc2a8b211dafb8c11c49c753d20cc1d9399161afd2413ed'),
(172, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:24', '79055d3fd381715942321dc9ba2f37df91d0ca5f694fc8508bb37ce2f46051cc'),
(173, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:25', 'f48d24216418481ac9bb9bbd94a8c7ae286e7607b7a436c8a28b360d29df20a4'),
(174, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:25', '4016435a9365480e3286df26d096e8c34cadea258cc2fdca657c3422ea85efd7'),
(175, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:27', 'ae5f9ce6d92554f14e3d75a3ac3443e873b5490e41336e96086a2418515ed088'),
(176, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:29', 'ed31f9005b5eac75edeab206379b2b640baf9a879d144cd532c769d550cbe9df'),
(177, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:37', '364cb247299a0cedeea82e28e8391f92e4d1132dd5e1eb1c68b6c675150b9f87'),
(178, 7, 'Viewed pending enrollment requests', '2026-03-24 15:16:41', '2d1ee6ebce77275505d9191459cf948f2228fd7ba0b06b2596692643f3dbd829'),
(179, 7, 'Viewed pending enrollment requests', '2026-03-24 15:17:50', '67bf82aa5cd35fe45c608fc98cb0092646d4313646e376d2b3289f0ca7e47c3e'),
(180, 7, 'Viewed pending enrollment requests', '2026-03-24 15:17:52', '210753e8a213e70cc8f7a096aeeb81c5186adc71d5114ec73fe4094787ec80b9'),
(181, 7, 'Viewed pending enrollment requests', '2026-03-24 15:17:56', '50df111e394799166ee568932a171954975e1d9d3a23c5be16fa08e53646d11a'),
(182, 7, 'Viewed pending enrollment requests', '2026-03-24 15:18:02', '4751ab7ec6a64dd20a857992fffc5061b16e84fd0daa1f7fcf5e22f68bf48713'),
(183, 7, 'Viewed pending enrollment requests', '2026-03-24 15:18:20', '8afd52ec2810a44ed700f7fac7203772222cf475942c4fbef7789801ae05bbe8'),
(184, 7, 'Viewed pending enrollment requests', '2026-03-24 15:18:43', '3e2635f10ceecea532ead2eefc2a01c0cc96c9f4b044452755716fa4ab0798e7'),
(185, 7, 'Viewed pending enrollment requests', '2026-03-24 15:18:46', '23bbc93d7d84d7c8e32cbf69a3964e7567451e57ee2848efed9502cdfe2d1d11'),
(186, 7, 'Viewed pending enrollment requests', '2026-03-24 15:18:51', 'cf07302f29217bd268ae83a239234905d3004192fdd90e4a32ad4d6c00161b67'),
(187, 7, 'Viewed pending enrollment requests', '2026-03-24 15:19:09', '7c971688deb1856eaae6292c0b2909d038fa5fcf9766f99bd013351e8258cd1d'),
(188, 7, 'Viewed pending enrollment requests', '2026-03-24 15:19:17', '3506530991a96a8429ae958640991c8a5342272c5e86e5e2836873168e799c5d'),
(189, 7, 'Viewed pending enrollment requests', '2026-03-24 15:21:09', '8b74b83386cdb12b6fda1b2f772074b72334934a21d909ab14f84e97eccbc162'),
(190, 7, 'Viewed pending enrollment requests', '2026-03-24 15:21:26', '15fa496e8a9747b2073560fe1a0e7813f1fa04e404d79015eae9b1def08fd283'),
(191, 7, 'Viewed pending enrollment requests', '2026-03-24 15:21:37', '51d16a43b7e0384bac45e9a7b04ef1a3a645d9411e5189ac526e96c8cbb5ee0d'),
(192, 7, 'Viewed pending enrollment requests', '2026-03-24 15:22:08', '418fe69b739249c28ca3c3df0ba34c29a1f568b7b9fd887ea5d2d8aa24e6ef90'),
(193, 7, 'Viewed pending enrollment requests', '2026-03-24 15:22:21', 'db0492c3b537f7023fa37a9f80482841a6623426145d4115417e04b754aee569'),
(194, 7, 'Viewed pending correction requests', '2026-03-24 15:27:41', 'd773169a28c92160e35376f848c76cdd5cb6a8606d9b6c2ab45a291f5e4f567f'),
(195, 7, 'Viewed pending enrollment requests', '2026-03-24 15:27:44', '9a8756011efc926d1d5167eb1ad2af41097cca50ff968c06abd362e9a4349f72'),
(196, 7, 'Viewed pending correction requests', '2026-03-24 15:27:46', 'e1ae48edd4f270ec7a9df7bdc036eed9b7c2a648a6d080146a767927c057a5d2'),
(197, 7, 'Viewed pending enrollment requests', '2026-03-24 15:27:47', '62388a880637bbd2e01b7ccf72e02bf94be5c1dce0bea1b797c67630f2dcbe6b'),
(198, 7, 'Viewed pending enrollment requests', '2026-03-24 15:31:33', '6429e9ed45083c9546a2f541123e10e1b2cc158974356b96c648e3b251b9e436'),
(199, 7, 'Viewed pending enrollment requests', '2026-03-24 15:31:35', '3286b9273c2ab508eadc0bb3bd9bd1eee26a93e74118fc2823dd4ba7daa2b92f'),
(200, 7, 'Viewed pending enrollment requests', '2026-03-24 15:34:46', '40d64c2474d1aac87a80ee1652b53481015624866e1532fe61ebd9fd0a0fb86b'),
(201, 7, 'Viewed pending enrollment requests', '2026-03-24 15:35:17', 'f7f02b226a86fe4abb4434dc74967468764a36e0ecc1062c07127d1440d87e57'),
(202, 7, 'Viewed pending enrollment requests', '2026-03-24 15:40:44', '4dd70bfcb37ec0841686f0cf6a7c711c514277c5436adea0db570bcbd61bced5'),
(203, 7, 'Viewed pending enrollment requests', '2026-03-24 15:40:49', 'e24b6d46bbfa93120a783f59174ed980e8cb313f7065599f804397cede2b5dde'),
(204, 7, 'Viewed pending enrollment requests', '2026-03-24 15:41:26', 'd7a92d78ec2d67aa85a2e9a76fc6e6442804aa77b5cfe6f8187ede70fe35fefc'),
(205, 7, 'Viewed pending enrollment requests', '2026-03-24 15:41:58', '895b960471ca16b8caf5f4942beb5fbb756b7eb0c49bd32c8e57d41f5057c6f9'),
(206, 7, 'Viewed pending enrollment requests', '2026-03-24 15:42:21', '057550ab9e95c5040ee804acf18fc6dc451956ceec38696285ba835367efed68'),
(207, 7, 'Viewed pending enrollment requests', '2026-03-24 15:42:27', '58015ffa60509d8fb19b635a80fddbbdb40a92abcf8ac9565b5586e69f7ec559'),
(208, 7, 'Viewed pending enrollment requests', '2026-03-24 15:42:48', 'cf30fe11bdd92fb79157afec98f0e0d500af85ed9b782a109c0697679774da51'),
(209, 7, 'Viewed pending enrollment requests', '2026-03-24 15:42:55', 'af2684ec4e2f74af88179dc30f7baa2e02a40d3e4a2c587817580fdb871b81bb'),
(210, 7, 'Viewed pending correction requests', '2026-03-24 15:54:39', 'f6d4640a61cc97f0fa55daef3668c5a3d5611fa44f7294d3c2b1ac4fbd1f0089'),
(211, 7, 'Viewed pending enrollment requests', '2026-03-24 15:54:43', 'd78659cbd7cc163f524bd90ca0546e130c4090bfe7467075b0f6231b48e536ee'),
(212, 7, 'Viewed pending enrollment requests', '2026-03-24 16:01:46', '4d185a03b5d12e55545a0f758bdc9c7cd88703a97aa324e7a0fb79eed2576e83'),
(213, 7, 'Viewed pending correction requests', '2026-03-24 16:01:49', '5fe915b42e2596aa6eca2d08f8fa1939dae707b8af059b16e4d4b6129ab6d54e'),
(214, 7, 'Viewed pending correction requests', '2026-03-24 16:01:52', '135376cf444d39d8d5839eb3bd52fda02fe30adae706ba3ff355d522bf3f1d37'),
(215, 7, 'Viewed pending enrollment requests', '2026-03-24 16:01:53', 'd893321313f6c2b0e9202a3cfd74b7d6837df7e95ab1ad3bc7ae3ced0b7ac742'),
(216, 7, 'Viewed pending enrollment requests', '2026-03-24 16:01:58', '79e5381c8e18834bf8f951996cd8ae81ec332e14b828cc3f66dd9bf7e49af02e'),
(217, 7, 'Viewed pending enrollment requests', '2026-03-24 16:02:02', 'd725f4e90027534bcfbbbb6193adb5d42461154df56965ec695f06e76be5a026'),
(218, 7, 'Viewed pending enrollment requests', '2026-03-24 16:02:03', '98985982fa25062c51122c9967b14e6eedfa6e0ef1a1742d36e6243befda6654'),
(219, 7, 'Approved enrollment request 6', '2026-03-24 16:02:10', '60d02d2fa192f536cbb1670c1b747448e16250f8dbb1ae01b5c61c538bbd732d'),
(220, 7, 'Viewed pending enrollment requests', '2026-03-24 16:02:21', 'add4cb78c780ab32782cc20479a2b7f19d65736cc34dc396cf0be64eb9a97215'),
(221, 7, 'Viewed pending enrollment requests', '2026-03-24 16:02:26', 'd23113eb55acc8a22a3200324d7d0802016e6a32a620c7e5a3f80ccc20a58aa5'),
(222, 7, 'Viewed pending enrollment requests', '2026-03-24 16:02:29', 'b73750a1ee48a51cb9a9f0c395e7dcd9ea9b503658fe87a5316a68268892b22e'),
(223, 7, 'Viewed pending enrollment requests', '2026-03-24 16:30:35', '7f12ae8d9fdd1ee292c0937917c7e14a22bf0139f1098ea5068fee34f22406ae'),
(224, 7, 'Viewed pending enrollment requests', '2026-03-24 17:36:10', '1e48cdc43cc1053b20fc2fda7fe814d1e2747ee3c259145df52513dfba21566a'),
(225, 14, 'User logged in', '2026-03-24 17:36:50', '6d3cf7a11916b5fa71be14a9c09d181cb4ff5e0932e125e3338bec97066bee62'),
(226, 14, 'Submitted enrollment request for subject 5', '2026-03-24 17:37:04', 'fb92bce30d665b9db4ecf82e1e60c71eeeb5093fce6b323166afa42d48c13632'),
(227, 7, 'User logged in', '2026-03-24 17:37:28', 'afaf4e072ed93a20c1f5470a575fcaeced1d22addc6bb2200ceb32bf9473ed16'),
(228, 7, 'Viewed pending enrollment requests', '2026-03-24 17:37:31', '2b9b17001ed2bdb9982c3e2e4360e09d39a91b28151d8e7da09fe09a626b6fc6'),
(229, 7, 'Viewed pending enrollment requests', '2026-03-24 17:37:33', 'b4a8ca65ad962eb1775fff0c8811b13d93ad6ae9758afd1930a6fb838056cf8c'),
(230, 7, 'Approved enrollment request 7', '2026-03-24 17:37:44', '10f514a9e186e9cad15af02c941426e0298d147e662abb8bf5101f5a820e3f8a'),
(231, 7, 'Viewed pending enrollment requests', '2026-03-24 17:37:44', '5e0e6fcca5c3d45c12e5fc4539280b951c1610cf5c1ea9109a130f4fc2045e97'),
(232, 2, 'User logged in', '2026-03-24 17:38:05', 'e37270e0c6a773279cf3b460fb11c8bb34e3bfbd3bdb80aaab0e49052606fffe'),
(233, 2, 'Encoded Prelim grade for student 14 in subject 2 (3rd Year - 2nd Semester): 78.25% (2.75)', '2026-03-24 17:39:11', 'e7255bbea219dfb870b5e4b06913257946c51ec310ae0e924e53903a1236d356'),
(234, 7, 'User logged in', '2026-03-24 17:39:45', 'fc9af703ab0c27c1ab5b22a481b32f064b81b3dacb3d92a415f86da29c57ca44'),
(235, 7, 'Viewed pending enrollment requests', '2026-03-24 17:39:48', '2beb1ddbee2f389f89c9f35b57ab5d4e06179c48622bb89285ea711e7c452ee8'),
(236, 7, 'Grade ID 18 approved by registrar.', '2026-03-24 17:43:08', '55725edc1f58840cd3e6c315bf1b1433acca3d6e7b3f74e717782f7e03b49b32'),
(237, 2, 'User logged in', '2026-03-24 17:43:26', 'd9a51aac6b14531d75b9e9411709885a90f1ab18a2eac42f20f54bda81b26977'),
(238, 2, 'Requested correction for grade ID 18', '2026-03-24 17:47:20', 'bbf225247a656a315fa777c92feaee43517debbe54fbad4c80cb6dde16d3a0e0'),
(239, 7, 'User logged in', '2026-03-24 17:47:37', 'c9e72e97e71519b8962d58628256054ba1d271237af3d7d5a6ea865afe9bfa7f'),
(240, 7, 'Viewed pending correction requests', '2026-03-24 17:47:40', '2ef2cb677470d462ee51562d857d13a241d0f8ea41d3efd1b2eff850d17ffb09'),
(241, 7, 'Approved correction request ID 2 for grade ID 18', '2026-03-24 17:47:53', '9e2894dad2e56c6fee2b54dcf85af5bb00f0f02cc9cc7c604528f91fa3581216'),
(242, 7, 'Viewed pending correction requests', '2026-03-24 17:47:53', '7d5c93907c9302215ced32d007438cc544adf19322924023d760f1d767f5114b'),
(243, 2, 'Encoded Prelim grade for student 14 in subject 2 (3rd Year - 2nd Semester): 86.25% (2)', '2026-03-24 17:48:28', '2c245063a495f31a49fa368923a5bd5222373126a0facb3a738e3b2f4497d751'),
(244, 7, 'Grade ID 18 approved by registrar.', '2026-03-24 17:48:49', 'b7eaa26b96cd94bef0c98bf348b040c7a158a602e21c04057dc20c1dec436798'),
(245, 7, 'Viewed pending correction requests', '2026-03-24 17:53:50', '3940cade6f1ab4fd3eebe216152b9fc8d0c3ae7bb88f570018c8d6364fbe5d9b'),
(246, 7, 'Viewed pending enrollment requests', '2026-03-24 17:53:52', 'bcd6987c344b79def659bd2933fa6c0d2fb9e6829eb648d18ef90c7fea5b345f'),
(247, 7, 'Viewed pending enrollment requests', '2026-03-24 17:53:57', '93a388ff388c9868b7c90022b92deb1cb78cd4bef72f43e26e99160ffaff906e'),
(248, 2, 'User logged in', '2026-03-25 03:43:15', '9bc76ba522391d8df60c56385f887aacc3650f9ced1dad073775bd129826ce9b'),
(249, 11, 'User logged in', '2026-03-25 04:00:03', '97b5d2a0881e21de2f751d84638ebf3e40e9e601537b09c9b2e10b460c41feaf'),
(250, 11, 'Created user: Mariane Penafiel (mariane.student@gmail.com)', '2026-03-25 04:00:58', 'd6ff9d8c332f5e6f32549fcd9b7c4fa857b47ed7e27625d725849f9b759e054e'),
(251, 15, 'User logged in', '2026-03-25 04:01:25', 'c55aa17609cc46f7492977693d8aba58fc4da2330dcffc8da39c55ceff31f269'),
(252, 15, 'Submitted enrollment request for subject 2', '2026-03-25 04:01:32', 'a853e2793a59b98b7af68bdf407b7d195bf976796bc963601e9868c57a8ebbfe'),
(253, 7, 'User logged in', '2026-03-25 04:01:46', '96604e61718b692353f6255c462d4f9e53ee6f25214417861d68d34d4e5af88b'),
(254, 7, 'Viewed pending enrollment requests', '2026-03-25 04:01:48', 'a4a157b2739a45ee8cff760d18854f9c487c5ec4ac5cd1edbf042cd51eda806c'),
(255, 7, 'Viewed pending enrollment requests', '2026-03-25 04:01:50', '0c84dd9bcd9fbc8446611bc8203035bd5f8f5ea167737ff7c380ed73124e1c3f'),
(256, 7, 'Approved enrollment request 8', '2026-03-25 04:01:58', '75f36abd5f421a13fde37d053c2f474c1a4fcd27b4bdd33b78d1a5edff337f3b'),
(257, 7, 'Viewed pending enrollment requests', '2026-03-25 04:01:59', '4f286969360e0e972d85d50df09d7157ef109aff2c8d989d60e735d17962ec7c'),
(258, 2, 'User logged in', '2026-03-25 04:02:15', 'ba231a7945c42fd0c51fd85cc9a68d063eb7d520d44c6c0d7413046ce422c70d'),
(259, 2, 'Encoded Prelim grade for student 15 in subject 2 (3rd Year - 2nd Semester): 80.25% (2.5)', '2026-03-25 04:19:05', '929f0fed4a862e296f5ab2a7c6288c4be01f0f5e7708e4e3af7374bb22465385'),
(260, 7, 'User logged in', '2026-03-25 04:21:07', '844665c07dd002960d2207e28b354eaea78d57bcb20a6f4986d474560cb189c8'),
(261, 7, 'Grade ID 20 approved by registrar.', '2026-03-25 04:21:29', '81fb0538bee436c4ccd36900532fcb59dde4ae67f49a8d23d06d3dafe1f2a406'),
(262, 2, 'User logged in', '2026-03-25 04:21:48', '090812d1fa2c7129f984d37d0aa7206ee109ad4fdeefc0959c54beece188e672'),
(263, 2, 'User logged in', '2026-03-25 13:18:50', 'd4b4bae87639b441749d4ea3558893b27b2b87de38bb2c06e472697bbbebe79c'),
(264, 2, 'Requested correction for grade ID 20', '2026-03-25 13:20:32', '41692b6ea4d8bb953ba57eace4f3f47b01d8ed454cca2682f8c6b5984e1564b1'),
(265, 7, 'User logged in', '2026-03-25 13:28:16', 'b66b01ef2d4d5a23fd2f4c1998bf0dfcc1e51b69a93a04cb3bb40ad1bdf08481'),
(266, 7, 'Viewed pending correction requests', '2026-03-25 13:28:20', '5687b89c99e90b7534c5d1d4091682c8a89ea889f8c6e9721401b690dcd6f564'),
(267, 2, 'User logged in', '2026-03-25 13:30:24', 'a26ba2529c6973563eb9eb89afb41c599d21e4969d524d5bea8fe08d38056544'),
(268, 7, 'User logged in', '2026-03-25 13:33:37', '81ea510eb1a47fbdfb05443e0bdbc41278f0ca32af365f5ad5228bdd9974ace4'),
(269, 7, 'Viewed pending correction requests', '2026-03-25 13:33:43', '244f455e59fb63e6ac95337b1c437279cfef4fa306ec34a44281cf36732bc60c'),
(270, 7, 'Approved correction request ID 3 for grade ID 20', '2026-03-25 13:33:52', '2739b6d7270952e1ec675ef37dbc545c93987d8f0ddce2fc90940f4bb712de47'),
(271, 7, 'Viewed pending correction requests', '2026-03-25 13:33:52', '68fe344d16ee82b9e78198df927a881000ff8f96566333bc3b40fe5806764695'),
(272, 2, 'Encoded Prelim grade for student 15 in subject 2 (3rd Year - 2nd Semester): 88.25% (2)', '2026-03-25 13:47:44', '408ab9b17a0e5e5d8b972ef8151aecffd6333e5cae405289a84c7c8ae42d55f4'),
(273, 7, 'Viewed pending correction requests', '2026-03-25 13:47:50', 'ce56ba6ac5c4931b45b15f670379511813d293d2a7eb59f642040c7051560721'),
(274, 7, 'Grade ID 20 approved by registrar.', '2026-03-25 13:48:01', '188fedd7b3dc9c9cde006e8b2beb09d941852e0eb7bfafc230eeeae1d4c6cbb9'),
(275, 7, 'Viewed pending enrollment requests', '2026-03-25 15:01:27', 'a98d57e0f4f6b0e1f0a6a705a094a1973ca3f6b68ee3bdba12a04a39fad89f32'),
(276, 7, 'Viewed pending correction requests', '2026-03-25 15:01:35', '443b39b650900198f34d76951fc56457a17b7db40dcca1ca9fe166bc77d14c96'),
(277, 7, 'Viewed pending enrollment requests', '2026-03-25 15:01:41', 'cccbf3a173a26bbd65cc7d10fcd05d919e26d639a9a5e3f2c4d500db9f31d9db'),
(278, 7, 'Viewed pending enrollment requests', '2026-03-25 15:01:46', '60444f8a6bd91a3858e13de658677b994cf62d3322a3ed69fbb691b8ff520415'),
(279, 11, 'User logged in', '2026-03-25 15:02:45', '17bd624b69ac9149c566018f6ee30fc16cc39130d81fed390a8701f77872423a'),
(280, 7, 'User logged in', '2026-03-25 15:03:54', '5520938b9a7e618afd016c97d5fef32addf0a62c8481ebcab124c0ffcdc6d3b9'),
(281, 7, 'Viewed pending enrollment requests', '2026-03-25 15:03:56', '26df9d629ceaa8f95378da006504e3d1e0b0d3c6a0f0366320e7fd4249b38d3f'),
(282, 7, 'Viewed pending correction requests', '2026-03-25 15:05:32', 'b85842a6bccdfa56888611715d176e7757cfafb4fa6cbbe87d94a4194b7b0a77'),
(283, 7, 'Viewed pending enrollment requests', '2026-03-25 15:05:33', 'e48b4d38ea7f7510b02a29710b86956e5695f34c624012964d777af0e1e3a5ee'),
(284, 7, 'Viewed pending correction requests', '2026-03-25 15:11:04', 'fd973ab52625f72a37051862bd4f213fd83ffd78bdd47961ebe01aef76c0bf2c'),
(285, 7, 'Viewed pending enrollment requests', '2026-03-25 15:11:05', '6f6d5d87a498c4016c95143d1be733c9af03dc6eebc3af2cfa39acc2d8e08685'),
(286, 7, 'Viewed pending enrollment requests', '2026-03-25 15:27:44', '4cfad108071999ff4a3eb28d396de3da0bec980655f41b793f42e2223eb553cc'),
(287, 7, 'Viewed pending enrollment requests', '2026-03-25 15:27:49', '167cd85d8068361164f2f75e0677ba7cf359d0b43b4bca74a7f359b8f9427b93'),
(288, 7, 'Viewed pending correction requests', '2026-03-25 15:27:55', 'e862f8e7e45ee5346dcf6c6504e34e97058459ccd58a64aa4aca109ca6df89e6'),
(289, 7, 'Viewed pending enrollment requests', '2026-03-25 15:27:58', '4b41982d2c105025c51fb95424c7cca1e8817e18e25bba0f45c617e1ac9a356c'),
(290, 7, 'Viewed pending enrollment requests', '2026-03-25 15:28:51', '07c19410d25d03faaa4558870da4325db6b995439989437207aa79c06167a328'),
(291, 7, 'Viewed pending enrollment requests', '2026-03-25 16:12:25', 'd5eb94f6ac07e533d3b274ce6de8e56f66cc910f8e2650bc492f137f0a95ffdd'),
(292, 7, 'Viewed pending correction requests', '2026-03-25 16:13:18', '277a9a25c9461b4fdbfc439b0ba841f56e3dc01524bca01c0f38f80d562fda85'),
(293, 7, 'Viewed pending enrollment requests', '2026-03-25 16:13:20', '8cf8cd8eb771632bdde42a4da396f4151d53a3d9d33107bbbf70da7ead38b28b'),
(294, 7, 'Viewed pending enrollment requests', '2026-03-25 16:13:46', 'e1c7d53734124dff3487e81bc34fa41372f96dd0085adbc8e16813d9f0edb6ce'),
(295, 7, 'Viewed pending enrollment requests', '2026-03-25 16:15:18', '77586ee2fe5e2f16530d30cd7914e3ae97cd97fc924f4aa5ecc6f942f45df7f6'),
(296, 7, 'Viewed pending enrollment requests', '2026-03-25 16:15:24', 'c57cb07825cd2fd24c6e02468d2db72ed0bc02f1562537dffa7457551d4a651b'),
(297, 7, 'Viewed pending enrollment requests', '2026-03-25 16:15:58', 'ac443c8130c813a4082986c9b504735cdf885eb49aea764638414ba50b09fe6a'),
(298, 7, 'Viewed pending enrollment requests', '2026-03-25 16:16:02', '691e2c5aac28836492f4121fc215fa5c03728230f7f4f0a5fa15b598adf2b859'),
(299, 7, 'Viewed pending enrollment requests', '2026-03-25 16:16:05', '69a8bb4f204a90007ad4cac75403c6f72f7f4f4f1c88fe15956fc47a71391e17'),
(300, 7, 'Viewed pending enrollment requests', '2026-03-25 16:16:08', '8d6bb112066ab3e34068f3dd55a18f4da4267a05905d9fde78a14b810fed51e0'),
(301, 7, 'Viewed pending enrollment requests', '2026-03-25 16:16:12', 'ecb3e292d9d1a87ff7c5a16d40884a6d887c947bf58d43858a19116302b8e69e'),
(302, 7, 'Viewed pending enrollment requests', '2026-03-25 16:16:15', '2e4da4df71e927e237c3f974959f6576b14a81b60e20e40d041164c52a055db0'),
(303, 7, 'Viewed pending enrollment requests', '2026-03-25 16:16:27', '028c8e6a2d0616b7bbf7750d515780a45b4c14f312d10a85c975db438022502d'),
(304, 7, 'Viewed pending enrollment requests', '2026-03-25 16:18:03', 'c3ddd1b0cbab533564066ee8bf1ae182c67be14509d5ace851b42864392f381a'),
(305, 7, 'Viewed pending enrollment requests', '2026-03-25 16:18:33', 'e9d8c6c02ba2c6b282026fca6c0036b2a4e06fd4670fed1f55bb2bc0fe4dc222'),
(306, 7, 'Viewed pending enrollment requests', '2026-03-25 16:18:37', '8e37a9d6781f0f1cd78b18de15a86e2d4993ca32075db7b15f305615fcefd068'),
(307, 7, 'Viewed pending enrollment requests', '2026-03-25 16:20:05', '861973b2d61ab5e668eacae62f8e31382d8023cbfd6a3e1059c6f47030973dcd'),
(308, 7, 'Viewed pending enrollment requests', '2026-03-25 16:21:09', 'b0777b85c6764e885ee6ab9ad07c7167128ac56586da0dd6e33c2a17d997573f'),
(309, 7, 'Viewed pending enrollment requests', '2026-03-25 16:21:16', '7423e2c7ab703c4d9b46aeee1ec94d89391999a9e13cadad3571235c2c1efdd5'),
(310, 7, 'Viewed pending enrollment requests', '2026-03-25 16:21:21', '13a999edb05f7712a5e45225312419c4b383431608db5826ce14bbbecd4e4ac6'),
(311, 7, 'Viewed pending enrollment requests', '2026-03-25 16:21:24', '937b2dbe792a7ebf98dfa9b34ed26ee2979b509f230b512885c4f271b2bbb5ec'),
(312, 7, 'Viewed pending enrollment requests', '2026-03-25 16:23:09', 'fa591a738409910e5c8534bdaf80c6c45484445f48c2a35688adacb5a5d2daa9'),
(313, 7, 'Viewed pending enrollment requests', '2026-03-25 16:23:33', '65faea55d53b3ed632ad09af268346038deceb25c78ab5eb5fc36c3c6984b177'),
(314, 7, 'Viewed pending enrollment requests', '2026-03-25 16:24:56', '71bf0c6eeab4f4cfbb4acb503904aee7662349c60aeacb82e8d9bcf8f87bb0e1'),
(315, 7, 'Viewed pending enrollment requests', '2026-03-25 16:25:05', 'bf8e220922a01a48e4d7f8471828efb55171a8deae7abc5e929d24cd3d3cd6d2'),
(316, 7, 'Viewed pending enrollment requests', '2026-03-25 16:25:08', '6d220d9fc1fab8086abd415e8516498c015a711d17818c69bbe3941f0822ffe6'),
(317, 7, 'Viewed pending enrollment requests', '2026-03-25 16:25:11', '80a26041e1d8d493336e724018458826f966cf8a5e7d6bbdcc624fb3853c3652'),
(318, 7, 'Viewed pending enrollment requests', '2026-03-25 16:25:14', '185586078e41a37bd08bd1d70c7e155acb7fc1ca3a8eea922d82efe302d5bbc9'),
(319, 7, 'Viewed pending enrollment requests', '2026-03-25 16:25:37', '5e6df71bf2838eefd1497f29fbe11aa5941bdd056d28af053ab54b79258b6b76'),
(320, 7, 'Viewed pending correction requests', '2026-03-25 16:25:41', '0eadc2d67dff1c4948443935809da0757579a599b9f9ff5569ca4f8ca4abfcb4'),
(321, 2, 'User logged in', '2026-03-25 16:25:54', '47f7ee116bf20a77412ab1e578b8d3f1751c5e973cdc5a97e3e819886e0b9d8a'),
(322, 7, 'User logged in', '2026-03-25 16:36:20', '131865b950c57d08be05c31b3f86b9111c41d4398cebea38f6a0514bfc6a60e8'),
(323, 7, 'Viewed pending enrollment requests', '2026-03-25 16:36:22', 'e7a9a862a9ac366cea0aa8f4dc13a051278c2a36ba2c05b84f0547351172c01c'),
(324, 7, 'Viewed pending enrollment requests', '2026-03-25 16:52:55', '53b3ea81c4c7cf930bc2b77dc47abbcbc7fb3c363cced38a0aae27d99f13ddac'),
(325, 7, 'Viewed pending enrollment requests', '2026-03-25 16:53:07', '1ced903b7e01186077d27d1f8b82af31fbcdc7ccbbc5a042f90f8f2a1fed0f45'),
(326, 7, 'Viewed pending correction requests', '2026-03-25 16:53:45', 'b6c8238191d6d5fc0360706450c0785408bcf1905d6fb0c52d6c4b20a8289271'),
(327, 7, 'Viewed pending enrollment requests', '2026-03-25 16:53:52', '2439a5a1ccb826e10e328fbff436d75cccc9e0784174b5350e65214718fb0a31'),
(328, 2, 'User logged in', '2026-03-26 02:14:15', '4c3ca887cb198230a1b0af64ff056f45f7cdf9f8bf15bdede5e74af004b53f06'),
(329, 7, 'User logged in', '2026-03-26 02:16:01', '2d598186ed5b1e1991ea3b56d26ff7953d09ebf6983f4391c374eca2581b0ee3'),
(330, 7, 'Viewed pending enrollment requests', '2026-03-26 02:16:23', 'e48e536b6d9558531e02853bd503424ba25238309d5264f08f70f0b61d7e2d81'),
(331, 7, 'Viewed pending correction requests', '2026-03-26 02:16:32', '7ae902826492c7c2686781df3630e8f1d9872b83fda6a1f4b76c626c02393802'),
(332, 7, 'Viewed pending enrollment requests', '2026-03-26 02:16:44', '7560f83035b42fd5ce8f10612f16b65284ae982673ab872b40c5f91bf559961c'),
(333, 7, 'Viewed pending correction requests', '2026-03-26 02:16:47', '05353457fe80ecf825c6a392158ab532e84e6b948839d46e0dce1fc3c03dc07f'),
(334, 7, 'Viewed pending enrollment requests', '2026-03-26 02:16:50', '692a0ff97e9184229ba3a0b82826be10be2147996e1a705e397bfaea23a5fda8'),
(335, 8, 'User logged in', '2026-03-26 02:17:12', '151ba415ab62bd29982bd4db7fe56b577be3f934cad365975eb2bf0a1a277b1e'),
(336, 11, 'User logged in', '2026-03-26 02:22:10', 'c7ec84d1d3dc59cf8939e5aaf31a59832cdaf08a4c6b60a5f0513b690da88f13'),
(337, 8, 'User logged in', '2026-03-26 02:22:49', '1ff8d8d8694a1f0688f8636a74a3a2fa7792c49a6ebb705db9854cfe9a6e2708'),
(338, 8, 'User logged in', '2026-03-26 03:13:49', 'b5096b0a93cdbfa6f4b9a0861440aa88b4c51c05abd2f6d55ae9cc3b69161d30'),
(339, 2, 'User logged in', '2026-03-26 03:22:50', '9fb52810791b0b1d1270602cd4eaf6315b5118f1df37b24cf0dacc9fbf9770df'),
(340, 11, 'User ID 15 deactivated: Mariane Penafiel', '2026-03-26 04:14:49', '967d69bfb2d940ac960bbb4b2be26d272fb4b23b98fe8326d1524ab0054ee304'),
(341, 11, 'User ID 15 activated: Mariane Penafiel', '2026-03-26 04:14:53', 'a77762c831cc79d33e5fe348f05e58254fed805e8553d188f6d7ffaa98b2e73c'),
(342, 8, 'User logged in', '2026-03-26 04:43:43', '15869cdaf007a095657146a199d46ff37f55324da6ec099f529c3ca0824645fe'),
(343, 2, 'User logged in', '2026-03-26 04:44:11', '5bdc6e42052b2737b50e81941b6fd0d9d6984afb71311866e82fc747355f2700'),
(344, 11, 'User logged in', '2026-03-26 04:48:10', '546d347705661611e466905758dfa2aced58f2c1da94b3d1db591fec693059ba'),
(345, 2, 'User logged in', '2026-03-26 05:52:10', '006a8f99db42afccd686e88c978a4b9ed685ab2f936afd7b99021c0a3cb02a68'),
(348, 11, 'User logged in from IP ::1', '2026-03-26 07:50:14', '0256b5aa8cebefadfab09ff34fb6d5c87907dc8d57f47a609ef474f6d0b8cecc'),
(361, 10, 'User logged in from IP ::1', '2026-03-26 07:58:02', '13821433b423b826529f3d08e22c54849d9cacd8e860614d56f81ba6fd9d0a3b'),
(368, 11, 'User logged in from IP ::1', '2026-03-26 08:03:29', 'd10dae89168214b69d2a4598587da9bc6addb74bc2ae0ce48099ed3f22378046'),
(370, 11, 'User logged in from IP ::1', '2026-03-26 08:09:14', '1fba6b6305b3d5414a10fd4663b1fabd014014b2fc24790278289afb464c46c3'),
(372, 10, 'User logged in from IP ::1', '2026-03-26 08:13:17', '484f4a139c18d2ae50c4d40d5eccd6784da753aa2056ce134e4f937f165815f4'),
(381, 8, 'User logged in from IP ::1', '2026-03-26 08:38:56', '7eae64fca677c6ba6a8e08217fd1baeeb5ebd06924698e822d3d5ab1e8ed9a66'),
(387, 8, 'User logged in from IP ::1', '2026-03-26 08:52:52', 'd007cdb94658533b1fea7240a13f2a2583a9b06e73ba36672d815e7e02d859a7'),
(388, 8, 'User logged in from IP ::1', '2026-03-26 09:00:04', '0c45c4c4dee8ebd1a12e5168ead487585eec46a91617455ebf837540a2447e35'),
(389, 2, 'User logged in from IP ::1', '2026-03-26 09:05:44', 'ea74c50fd976d1d3fcabf4e901dc186383c83484b35a3a1939c960b86308f248'),
(391, 7, 'User logged in from IP ::1', '2026-03-26 09:17:17', '8f1643d2f55503669afe4a0e39add3d5f97de0800cb052044bfc2c82fd5f0660'),
(392, 7, 'Viewed pending enrollment requests', '2026-03-26 09:17:24', 'e5ab45c27bb81045f7ba7acf8a61203185bf9061eea5e3f4e20f030d5ac4c80c'),
(393, 7, 'Viewed pending correction requests', '2026-03-26 09:17:31', 'c5ac70511ee49e147c2f56c2598e9c4b59a31943a6698a24477794ca229ed26f'),
(394, 7, 'Viewed pending enrollment requests', '2026-03-26 09:17:37', '018014389ca1dd8b6bfcef031dc07b8aff4254e65daba6582e42e1052c99ace7'),
(395, 11, 'User logged in from IP ::1', '2026-03-26 09:17:51', '7fb3e9329e928897f595eb74091090c45305818c83a307f98648ec3bc7a87b8d'),
(396, 8, 'User logged in from IP ::1', '2026-03-26 09:19:16', '552fa7cd670b3c4d180f7fd8d589067f6f5f611dd875065befe696c5204e90aa'),
(397, 8, 'User logged in from IP ::1', '2026-03-26 10:04:30', 'caf7b00ba1a475cfed69284516a17247f508613943aac7d516188f1cddf9496a');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `subject_id`, `semester_id`, `status`) VALUES
(1, 12, 2, 2, 'Active'),
(2, 9, 2, 2, 'Active'),
(3, 10, 2, 2, 'Active'),
(4, 8, 2, 2, 'Active'),
(5, 13, 2, 2, 'Active'),
(6, 14, 2, 2, 'Active'),
(7, 14, 5, 2, 'Active'),
(8, 15, 2, 2, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_requests`
--

CREATE TABLE `enrollment_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `decision_date` timestamp NULL DEFAULT NULL,
  `registrar_id` int(11) DEFAULT NULL,
  `decision_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_requests`
--

INSERT INTO `enrollment_requests` (`request_id`, `student_id`, `subject_id`, `status`, `request_date`, `decision_date`, `registrar_id`, `decision_notes`) VALUES
(1, 13, 2, 'Approved', '2026-03-24 04:35:00', '2026-03-24 04:37:05', 7, ''),
(2, 8, 2, 'Approved', '2026-03-24 04:35:26', '2026-03-24 04:37:03', 7, ''),
(3, 10, 2, 'Approved', '2026-03-24 04:35:42', '2026-03-24 04:37:00', 7, ''),
(4, 9, 2, 'Approved', '2026-03-24 04:35:59', '2026-03-24 04:36:56', 7, ''),
(5, 12, 2, 'Approved', '2026-03-24 04:36:22', '2026-03-24 04:36:50', 7, ''),
(6, 14, 2, 'Approved', '2026-03-24 15:00:48', '2026-03-24 16:02:10', 7, NULL),
(7, 14, 5, 'Approved', '2026-03-24 17:37:04', '2026-03-24 17:37:44', 7, NULL),
(8, 15, 2, 'Approved', '2026-03-25 04:01:32', '2026-03-25 04:01:58', 7, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `academic_period` varchar(50) NOT NULL DEFAULT '3rd Year - 2nd Semester',
  `term` enum('Prelim','Midterm','Finals') NOT NULL DEFAULT 'Prelim',
  `percentage` decimal(5,2) NOT NULL,
  `numeric_grade` decimal(3,2) NOT NULL,
  `remarks` varchar(20) NOT NULL,
  `status` enum('Pending','Returned','Approved') DEFAULT 'Pending',
  `is_locked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`grade_id`, `student_id`, `subject_id`, `academic_period`, `term`, `percentage`, `numeric_grade`, `remarks`, `status`, `is_locked`) VALUES
(1, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 76.75, 3.00, 'Passed', 'Approved', 1),
(3, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 80.15, 2.50, 'Good', 'Approved', 1),
(4, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 77.75, 2.75, 'Satisfactory', 'Approved', 1),
(5, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 77.75, 2.75, 'Satisfactory', 'Approved', 1),
(6, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 81.25, 2.50, 'Good', 'Approved', 1),
(18, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 86.25, 2.00, 'Good', 'Approved', 1),
(20, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 88.25, 2.00, 'Good', 'Approved', 1);

-- --------------------------------------------------------

--
-- Table structure for table `grade_categories`
--

CREATE TABLE `grade_categories` (
  `category_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term` enum('Prelim','Midterm','Finals') NOT NULL DEFAULT 'Prelim',
  `category_name` varchar(100) NOT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 0.00,
  `input_mode` enum('raw','percentage') NOT NULL DEFAULT 'raw'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_categories`
--

INSERT INTO `grade_categories` (`category_id`, `subject_id`, `term`, `category_name`, `weight`, `input_mode`) VALUES
(37, 2, 'Midterm', 'Attendance', 10.00, 'percentage'),
(38, 2, 'Midterm', 'Assessment', 25.00, 'raw'),
(39, 2, 'Midterm', 'Project', 25.00, 'percentage'),
(40, 2, 'Midterm', 'Exam', 40.00, 'raw'),
(44, 2, 'Finals', 'Attendance', 10.00, 'percentage'),
(45, 2, 'Finals', 'Assessment', 25.00, 'raw'),
(46, 2, 'Finals', 'Project', 25.00, 'percentage'),
(47, 2, 'Finals', 'Exam', 40.00, 'raw'),
(56, 2, 'Prelim', 'Attendance', 10.00, 'percentage'),
(57, 2, 'Prelim', 'Class Standing', 25.00, 'raw'),
(58, 2, 'Prelim', 'Project', 25.00, 'percentage'),
(59, 2, 'Prelim', 'Exam', 40.00, 'raw');

-- --------------------------------------------------------

--
-- Table structure for table `grade_category_items`
--

CREATE TABLE `grade_category_items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_label` varchar(100) NOT NULL,
  `item_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_category_items`
--

INSERT INTO `grade_category_items` (`item_id`, `category_id`, `item_label`, `item_order`) VALUES
(9, 56, 'Prelim', 1),
(10, 57, 'Quiz #1', 1),
(11, 57, 'Quiz #2', 2),
(12, 57, 'Quiz #3', 3),
(13, 57, 'Group Performance #1', 4),
(14, 57, 'Group Performance #2', 5),
(15, 58, 'Integrated System', 1),
(16, 59, 'Prelim', 1);

-- --------------------------------------------------------

--
-- Table structure for table `grade_components`
--

CREATE TABLE `grade_components` (
  `grade_component_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `academic_period` varchar(50) NOT NULL,
  `term` enum('Prelim','Midterm','Finals') NOT NULL DEFAULT 'Prelim',
  `component_id` int(11) NOT NULL,
  `raw_score` decimal(6,2) NOT NULL,
  `max_score` decimal(6,2) NOT NULL,
  `category_id` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_components`
--

INSERT INTO `grade_components` (`grade_component_id`, `student_id`, `subject_id`, `academic_period`, `term`, `component_id`, `raw_score`, `max_score`, `category_id`, `item_id`) VALUES
(49, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 80.00, 100.00, 56, 9),
(50, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 10),
(51, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 11),
(52, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 12),
(53, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 13),
(54, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 14),
(55, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(56, 12, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 35.00, 50.00, 59, 16),
(57, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 90.00, 100.00, 56, 9),
(58, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 10),
(59, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 11),
(60, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 12),
(61, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 13),
(62, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 14),
(63, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(64, 10, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 38.00, 50.00, 59, 16),
(65, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 90.00, 100.00, 56, 9),
(66, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 10),
(67, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 11),
(68, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 12),
(69, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 13),
(70, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 14),
(71, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(72, 13, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 35.00, 50.00, 59, 16),
(73, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 90.00, 100.00, 56, 9),
(74, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 10),
(75, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 11),
(76, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 12),
(77, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 13),
(78, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 14),
(79, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(80, 9, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 35.00, 50.00, 59, 16),
(81, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 70.00, 100.00, 56, 9),
(82, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 9.00, 10.00, 57, 10),
(83, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 9.00, 10.00, 57, 11),
(84, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 9.00, 10.00, 57, 12),
(85, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 13),
(86, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 14),
(87, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(88, 8, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 40.00, 50.00, 59, 16),
(177, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 80.00, 100.00, 56, 9),
(178, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 9.00, 10.00, 57, 10),
(179, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 9.00, 10.00, 57, 11),
(180, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 9.00, 10.00, 57, 12),
(181, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 13),
(182, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 15.00, 20.00, 57, 14),
(183, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(184, 14, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 45.00, 50.00, 59, 16),
(193, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 100.00, 100.00, 56, 9),
(194, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 10),
(195, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 11),
(196, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 8.00, 10.00, 57, 12),
(197, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 18.00, 20.00, 57, 13),
(198, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 18.00, 20.00, 57, 14),
(199, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 85.00, 100.00, 58, 15),
(200, 15, 2, '3rd Year - 2nd Semester', 'Prelim', 0, 45.00, 50.00, 59, 16);

-- --------------------------------------------------------

--
-- Table structure for table `grade_corrections`
--

CREATE TABLE `grade_corrections` (
  `request_id` int(11) NOT NULL,
  `grade_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `registrar_id` int(11) DEFAULT NULL,
  `decision_notes` text DEFAULT NULL,
  `decision_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_corrections`
--

INSERT INTO `grade_corrections` (`request_id`, `grade_id`, `faculty_id`, `reason`, `status`, `request_date`, `registrar_id`, `decision_notes`, `decision_date`) VALUES
(1, 1, 2, 'Missed items.', 'Approved', '2026-03-24 05:28:24', 7, 'Request approved based on corrected calculation of total points.', '2026-03-24 05:28:42'),
(2, 18, 2, 'Missed items.', 'Approved', '2026-03-24 17:47:19', 7, NULL, '2026-03-24 17:47:53'),
(3, 20, 2, 'Missed items.', 'Approved', '2026-03-25 13:20:32', 7, NULL, '2026-03-25 13:33:52');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL COMMENT 'email address or IP address',
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores failed login attempts for brute-force detection';

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`attempt_id`, `identifier`, `ip_address`, `attempted_at`) VALUES
(35, 'carl.student@gmail.com', '::1', '2026-03-26 08:52:04'),
(36, 'carl.student@gmail.com', '::1', '2026-03-26 08:52:13'),
(37, 'carl.student@gmail.com', '::1', '2026-03-26 08:52:19'),
(38, 'carl.student@gmail.com', '::1', '2026-03-26 08:52:26'),
(39, 'carl.student@gmail.com', '::1', '2026-03-26 08:52:32');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 12, 'Your enrollment request #5 was approved.', 0, '2026-03-24 04:36:50'),
(2, 9, 'Your enrollment request #4 was approved.', 0, '2026-03-24 04:36:57'),
(3, 10, 'Your enrollment request #3 was approved.', 0, '2026-03-24 04:37:00'),
(4, 8, 'Your enrollment request #2 was approved.', 0, '2026-03-24 04:37:03'),
(5, 13, 'Your enrollment request #1 was approved.', 0, '2026-03-24 04:37:05'),
(6, 2, 'Your correction request #1 was approved; grade unlocked for resubmission.', 0, '2026-03-24 05:28:42'),
(7, 14, 'Your enrollment request #6 was approved.', 0, '2026-03-24 16:02:10'),
(8, 14, 'Your enrollment request #7 was approved.', 0, '2026-03-24 17:37:44'),
(9, 2, 'Your correction request #2 was approved; grade unlocked for resubmission.', 0, '2026-03-24 17:47:53'),
(10, 15, 'Your enrollment request #8 was approved.', 0, '2026-03-25 04:01:58'),
(11, 2, 'Your correction request #3 was approved; grade unlocked for resubmission.', 0, '2026-03-25 13:33:52');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(4, 'Admin'),
(1, 'Faculty'),
(2, 'Registrar'),
(3, 'Student');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `semester_id` int(11) NOT NULL,
  `semester_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`semester_id`, `semester_name`) VALUES
(1, 'First Semester'),
(2, 'Second Semester');

-- --------------------------------------------------------

--
-- Table structure for table `semestral_grades`
--

CREATE TABLE `semestral_grades` (
  `semestral_grade_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `academic_period` varchar(50) NOT NULL,
  `prelim_grade` decimal(5,2) DEFAULT NULL,
  `midterm_grade` decimal(5,2) DEFAULT NULL,
  `finals_grade` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `final_numeric` decimal(3,2) DEFAULT NULL,
  `final_remarks` varchar(20) DEFAULT NULL,
  `status` enum('Draft','Submitted','Approved') DEFAULT 'Draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semestral_grades`
--

INSERT INTO `semestral_grades` (`semestral_grade_id`, `student_id`, `subject_id`, `academic_period`, `prelim_grade`, `midterm_grade`, `finals_grade`, `final_grade`, `final_numeric`, `final_remarks`, `status`, `submitted_at`, `submitted_by`) VALUES
(1, 12, 2, '3rd Year - 2nd Semester', 76.75, 0.00, 0.00, 23.03, 5.00, 'Failed', 'Approved', '2026-03-24 05:30:52', 2),
(2, 10, 2, '3rd Year - 2nd Semester', 80.15, 0.00, 0.00, 24.05, 5.00, 'Failed', 'Approved', '2026-03-24 05:30:52', 2),
(3, 13, 2, '3rd Year - 2nd Semester', 77.75, 0.00, 0.00, 23.33, 5.00, 'Failed', 'Approved', '2026-03-24 05:30:53', 2),
(4, 9, 2, '3rd Year - 2nd Semester', 77.75, 0.00, 0.00, 23.33, 5.00, 'Failed', 'Approved', '2026-03-24 05:30:53', 2),
(5, 8, 2, '3rd Year - 2nd Semester', 81.25, 0.00, 0.00, 24.38, 5.00, 'Failed', 'Approved', '2026-03-24 05:30:53', 2);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `faculty_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `faculty_id`) VALUES
(1, 'SP101', 'Social and Professional Issues', 1),
(2, 'IAS102', 'Information Assurance and Security 2', 2),
(3, 'TEC101', 'Technopreneurship', 3),
(4, 'PM101', 'Business Process Management in IT', 4),
(5, 'ITSP2A', 'Mobile Application and Development', 5),
(6, 'SA101', 'System Administration And Maintenance', 6);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `program` varchar(100) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `year_level` tinyint(4) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `program`, `section`, `year_level`, `email`, `password_hash`, `role_id`, `created_at`, `is_active`) VALUES
(1, 'Jacqueline De Guzman', NULL, NULL, NULL, 'jacqueline.faculty@gmail.com', '$2y$10$oF94e4rJ.3I35Y0y7AubM.j8rXDVk/eDWi0Dr5y23imGK8TXWg4BO', 1, '2026-02-25 11:55:09', 1),
(2, 'Andrew Delacruz', NULL, NULL, NULL, 'andrew.faculty@gmail.com', '$2y$10$UI9UGiAMDZ/KKGinvalEh.MW7T.XP5quI1CIMxTyEa.O.S54w90QO', 1, '2026-02-25 11:56:58', 1),
(3, 'Marimel Loya', NULL, NULL, NULL, 'marimel.faculty@gmail.com', '$2y$10$NGfJOjXlAB1kIXLiLbwHO.sOF4aoilu5swLolOhlSbxUL7owBn6gG', 1, '2026-02-25 11:57:51', 1),
(4, 'Jorge Lucero', NULL, NULL, NULL, 'jorge.faculty@gmail.com', '$2y$10$NeesYvAJWn3mCPJLswzN5uMzgYFF9RnUSJeq2knesEOSkLXJagnr6', 1, '2026-02-25 11:58:57', 1),
(5, 'Jessa Brogada', NULL, NULL, NULL, 'jessa.faculty@gmail.com', '$2y$10$8Z.9dD4ioG5u0mgqkJ4D8.lIk5LQDZCcKAd7MXOClufqs8Zz8hMTq', 1, '2026-02-25 11:59:35', 1),
(6, 'Regane Macahibag', NULL, NULL, NULL, 'regane.faculty@gmail.com', '$2y$10$AZJAx4d6CpMdg5/ynSpcPONnxKotoR1Ju6k.1ECwayLHHw33r0x7m', 1, '2026-02-25 12:00:20', 1),
(7, 'Eva Arce', NULL, NULL, NULL, 'eva.registrar@gmail.com', '$2y$10$1.G8TngCS/DesxJ1C001t.RQaQ/33zfuKF590Act5U0imcIYyh64i', 2, '2026-02-25 12:01:50', 1),
(8, 'Yuan Amboy', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'yuan.student@gmail.com', '$2y$10$RDwalh4Be87BFTC3TFnvD.ChJVPmBnIecdzqSrSlDYnLjcbKpUPza', 3, '2026-02-25 12:05:33', 1),
(9, 'Roberto Fuentes', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'roberto.student@gmail.com', '$2y$10$m9xGsiOC7DTd6q/rpCCRMO0lclg5s/eynIGPFVxFdN2MlDfsDs2j6', 3, '2026-03-04 14:00:29', 1),
(10, 'Carl Garcia', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'carl.student@gmail.com', '$2y$10$4s4wuxzvitObJLzuIRjuG.CWIcH.wgp7v2dGZxs59O.VuooM03qNu', 3, '2026-03-05 10:06:23', 1),
(11, 'Espada Admin', NULL, NULL, NULL, 'espada.admin@gmail.com', '$2y$10$FOF2NjMXzE8bJReNSqg93uOLQaBbA10LBBFAFDbKm2wHXMz9jbC02', 4, '2026-03-05 19:18:37', 1),
(12, 'Adrian Aseo', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'adrian.student@gmail.com', '$2y$10$Fi3kKBlVleLhsWfwPHUaP.Dhftc2giL9WRPdkujiLsV76geE3wO3e', 3, '2026-03-05 19:46:46', 1),
(13, 'Kurt Magallanes', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'kurt.student@gmail.com', '$2y$10$nUCfxrKO3qm3Inn5ufrrHOuQCt105YDmx9r/HYF64rbGG4dvdnj8i', 3, '2026-03-24 04:34:15', 1),
(14, 'Mark Pagdilao', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'mark.student@gmail.com', '$2y$10$wsagovIk/d74pXCd92FQ5ufFGnnSNrQ5DRxS5XkvCILuRy7Rx7Ifu', 3, '2026-03-24 15:00:25', 1),
(15, 'Mariane Penafiel', 'Bachelor of Science in Information Technology', 'BSIT-32011-IM', 3, 'mariane.student@gmail.com', '$2y$10$TogOTrWhs9oz/R4J1FV8kuvETEznTM.76mmVTf1M0cqDnukoC7Nqa', 3, '2026-03-25 04:00:58', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_audit_hmac` (`row_hmac`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `semester_id` (`semester_id`);

--
-- Indexes for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `registrar_id` (`registrar_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD UNIQUE KEY `unique_grade` (`student_id`,`subject_id`,`academic_period`,`term`),
  ADD KEY `grades_fk_subject` (`subject_id`);

--
-- Indexes for table `grade_categories`
--
ALTER TABLE `grade_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `grade_category_items`
--
ALTER TABLE `grade_category_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `grade_category_items_ibfk_1` (`category_id`);

--
-- Indexes for table `grade_components`
--
ALTER TABLE `grade_components`
  ADD PRIMARY KEY (`grade_component_id`),
  ADD UNIQUE KEY `unique_grade_component` (`student_id`,`subject_id`,`academic_period`,`term`,`category_id`,`item_id`),
  ADD KEY `fk_gc_subject` (`subject_id`),
  ADD KEY `fk_gc_component` (`component_id`),
  ADD KEY `grade_components_ibfk_1` (`category_id`),
  ADD KEY `grade_components_ibfk_2` (`item_id`);

--
-- Indexes for table `grade_corrections`
--
ALTER TABLE `grade_corrections`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `registrar_id` (`registrar_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_time` (`attempted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`semester_id`);

--
-- Indexes for table `semestral_grades`
--
ALTER TABLE `semestral_grades`
  ADD PRIMARY KEY (`semestral_grade_id`),
  ADD UNIQUE KEY `unique_semestral` (`student_id`,`subject_id`,`academic_period`),
  ADD KEY `sg_fk_subject` (`subject_id`),
  ADD KEY `sg_fk_submitted` (`submitted_by`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=398;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `grade_categories`
--
ALTER TABLE `grade_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `grade_category_items`
--
ALTER TABLE `grade_category_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `grade_components`
--
ALTER TABLE `grade_components`
  MODIFY `grade_component_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=209;

--
-- AUTO_INCREMENT for table `grade_corrections`
--
ALTER TABLE `grade_corrections`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `semestral_grades`
--
ALTER TABLE `semestral_grades`
  MODIFY `semestral_grade_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`);

--
-- Constraints for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD CONSTRAINT `enrollment_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `enrollment_requests_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `enrollment_requests_ibfk_3` FOREIGN KEY (`registrar_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `grades_fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `grade_categories`
--
ALTER TABLE `grade_categories`
  ADD CONSTRAINT `grade_categories_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `grade_category_items`
--
ALTER TABLE `grade_category_items`
  ADD CONSTRAINT `grade_category_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `grade_categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `grade_components`
--
ALTER TABLE `grade_components`
  ADD CONSTRAINT `fk_gc_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_gc_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `grade_components_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `grade_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grade_components_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `grade_category_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `grade_corrections`
--
ALTER TABLE `grade_corrections`
  ADD CONSTRAINT `grade_corrections_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`grade_id`),
  ADD CONSTRAINT `grade_corrections_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `grade_corrections_ibfk_3` FOREIGN KEY (`registrar_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `semestral_grades`
--
ALTER TABLE `semestral_grades`
  ADD CONSTRAINT `sg_fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `sg_fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `sg_fk_submitted` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
