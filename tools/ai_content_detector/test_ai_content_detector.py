import tempfile
import unittest
from pathlib import Path
from unittest.mock import Mock, patch

import ai_content_detector as detector


def _repeat_words(base_words: list[str], count: int) -> str:
    words = []
    while len(words) < count:
        words.extend(base_words)
    return " ".join(words[:count])


class TestAIContentDetector(unittest.TestCase):
    def test_analyze_text_returns_none_when_too_short(self) -> None:
        text = "Hello world. This is short. Still short!"
        self.assertIsNone(detector.analyze_text(text))

    def test_analyze_text_humanish_verdict(self) -> None:
        text = (
            "Yesterday I walked to the market. "
            "Today my neighbor described a surprising mistake in a long, detailed story about repainting a hallway while balancing tools and a radio. "
            "Afterward we laughed. "
            "Meanwhile the kettle hissed, the dog barked once, and I wrote several notes about errands, schedules, and small repairs for the weekend. "
            "Suddenly the sun returned. "
            "Tonight I will finish a chapter, cook rice with vegetables, and email a friend about tickets for a local concert next month. "
            "Eventually I went outside. "
            "Finally I slept."
        )
        result = detector.analyze_text(text)
        self.assertIsNotNone(result)
        assert result is not None
        self.assertEqual(result["verdict"], "LIKELY HUMAN")
        self.assertLess(result["ai_score"], 35)

    def test_analyze_text_aiish_verdict(self) -> None:
        base = [
            "moreover",
            "however",
            "therefore",
            "additionally",
            "robust",
            "leverage",
            "streamline",
        ]
        sentence = _repeat_words(base, 18).capitalize() + "."
        paragraph = " ".join([sentence for _ in range(8)])
        text = "\n\n".join([paragraph, paragraph, paragraph])

        result = detector.analyze_text(text)
        self.assertIsNotNone(result)
        assert result is not None
        self.assertIn(result["verdict"], {"POSSIBLY AI", "LIKELY AI"})
        self.assertGreaterEqual(result["ai_score"], 35)

    def test_extract_text_from_html_removes_tags(self) -> None:
        html = """
        <html>
          <head><style>.x{color:red}</style></head>
          <body>
            <header>Menu</header>
            <nav>Links</nav>
            <article><p>Hello world.</p><p>Second paragraph here.</p></article>
            <script>console.log("x")</script>
            <footer>Footer</footer>
          </body>
        </html>
        """
        text = detector._extract_text_from_html(html)
        self.assertIn("Hello world.", text)
        self.assertIn("Second paragraph here.", text)
        self.assertNotIn("console.log", text)
        self.assertNotIn("Menu", text)
        self.assertNotIn("Links", text)
        self.assertNotIn("Footer", text)

    def test_fetch_text_validates_scheme(self) -> None:
        with self.assertRaises(ValueError):
            detector.fetch_text("ftp://example.com")

    def test_fetch_text_uses_requests_and_parses(self) -> None:
        response = Mock()
        response.text = (
            "<html><body><p>Hello world.</p><p>More text here.</p></body></html>"
        )
        response.raise_for_status = Mock()

        session = Mock()
        session.get = Mock(return_value=response)

        with patch("requests.Session", return_value=session):
            text = detector.fetch_text("https://example.com")

        self.assertIn("Hello world.", text)
        self.assertIn("More text here.", text)

    def test_load_text_from_file_and_html(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            path = Path(tmpdir) / "page.html"
            path.write_text(
                "<html><body><p>A paragraph.</p></body></html>", encoding="utf-8"
            )
            text = detector._load_text_from_args(
                None, str(path), None, detector.FetchConfig()
            )
            self.assertIn("A paragraph.", text)

    def test_main_returns_error_on_missing_input(self) -> None:
        code = detector.main(["--log-level", "ERROR"])
        self.assertEqual(code, 1)

    def test_main_runs_on_text_input(self) -> None:
        text = (
            "One sentence is here and has enough words to count properly for the analysis. "
            "Another sentence arrives with a different length, different vocabulary, and a slightly different rhythm to create variance. "
            "A third sentence adds more variety and avoids repeating the same short phrases across the sample. "
            "The fourth sentence continues with extra unique words for this particular test case, including animals, places, and actions. "
            "A fifth sentence expands the sample again with more details, numbers, and varied structure to increase the total word count. "
            "Finally, the sixth sentence concludes this longer sample text so the detector has sufficient content to score."
        )
        code = detector.main(["--text", text, "--log-level", "ERROR"])
        self.assertEqual(code, 0)


if __name__ == "__main__":
    unittest.main()
